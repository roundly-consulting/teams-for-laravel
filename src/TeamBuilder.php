<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Teams\Actions\AddMemberAction;
use RoundlyConsulting\Teams\Actions\ApproveJoinRequestAction;
use RoundlyConsulting\Teams\Actions\ChangeMemberRoleAction;
use RoundlyConsulting\Teams\Actions\CreateInviteAction;
use RoundlyConsulting\Teams\Actions\DefineTeamRoleAction;
use RoundlyConsulting\Teams\Actions\DenyJoinRequestAction;
use RoundlyConsulting\Teams\Actions\RemoveMemberAction;
use RoundlyConsulting\Teams\Actions\RequestToJoinAction;
use RoundlyConsulting\Teams\Actions\ResendInviteAction;
use RoundlyConsulting\Teams\Actions\RevokeInviteAction;
use RoundlyConsulting\Teams\Actions\TransferOwnershipAction;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateInviteData;
use RoundlyConsulting\Teams\DataTransferObjects\DefineTeamRoleData;
use RoundlyConsulting\Teams\DataTransferObjects\RequestToJoinData;
use RoundlyConsulting\Teams\DataTransferObjects\RespondToJoinRequestData;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Roles\Permission;
use RoundlyConsulting\Teams\Settings\TeamSettings;

final class TeamBuilder
{
    /** @var list<Model> */
    private array $approvalApprovers = [];

    private ?ApprovalRule $approvalRule = null;

    private ?int $approvalQuorum = null;

    public function __construct(
        private readonly Team $team,
    ) {}

    /** @param array<string, mixed> $meta */
    public function addMember(Model $member, string $role, array $meta = [], ?CarbonInterface $expiresAt = null): self
    {
        app(AddMemberAction::class)->execute($this->team, new AddMemberData(
            member: $member,
            role: $role,
            meta: $meta,
            expiresAt: $expiresAt,
        ));

        return $this;
    }

    public function removeMember(Model $member): self
    {
        app(RemoveMemberAction::class)->execute($this->team, $member);

        return $this;
    }

    public function changeRole(Model $member, string $role): self
    {
        $found = $this->team->findMember($member);

        if ($found !== null) {
            app(ChangeMemberRoleAction::class)->execute($found, $role);
        }

        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function invite(
        string $role,
        ?CarbonInterface $expiresAt = null,
        ?string $email = null,
        ?Model $invitedBy = null,
        array $meta = [],
        ?int $maxUses = 1,
    ): Invite {
        return app(CreateInviteAction::class)->execute($this->team, new CreateInviteData(
            role: $role,
            expiresAt: $expiresAt,
            email: $email,
            invitedBy: $invitedBy,
            meta: $meta,
            maxUses: $maxUses,
        ));
    }

    public function resendInvite(Invite $invite): Invite
    {
        return app(ResendInviteAction::class)->execute($invite);
    }

    public function revokeInvite(Invite $invite): bool
    {
        return app(RevokeInviteAction::class)->execute($invite);
    }

    /** @param list<string|Permission> $permissions */
    public function defineRole(string $key, string $name, array $permissions = [], string $description = ''): TeamRole
    {
        return app(DefineTeamRoleAction::class)->execute(new DefineTeamRoleData(
            teamId: (int) $this->team->getKey(),
            key: $key,
            name: $name,
            permissions: $permissions,
            description: $description,
        ));
    }

    public function approveJoinRequest(JoinRequest $request, Model $responder, ?string $role = null): Member
    {
        return app(ApproveJoinRequestAction::class)->execute($request, new RespondToJoinRequestData(
            responder: $responder,
            role: $role,
        ));
    }

    public function denyJoinRequest(JoinRequest $request, Model $responder): JoinRequest
    {
        return app(DenyJoinRequestAction::class)->execute($request, new RespondToJoinRequestData(
            responder: $responder,
        ));
    }

    /**
     * Opt into multi-admin sign-off for the next requestToJoinFor(): the join
     * request is opened pending, an ApprovalRequest is created for the given
     * approvers, and SyncJoinRequestStatusFromApproval mirrors the engine's
     * decision back onto the request. The native single-responder
     * approveJoinRequest/denyJoinRequest path is untouched.
     *
     * @param  Model|iterable<int, Model>  $approvers
     */
    public function requireApprovalFrom(Model|iterable $approvers): self
    {
        $this->approvalApprovers = $approvers instanceof Model
            ? [$approvers]
            : [...$approvers];

        return $this;
    }

    public function rule(ApprovalRule $rule): self
    {
        $this->approvalRule = $rule;

        return $this;
    }

    public function quorum(?int $quorum): self
    {
        $this->approvalQuorum = $quorum;

        return $this;
    }

    /**
     * Open a join request for the requester. When approvers were staged via
     * requireApprovalFrom() and teams.approvals.enabled is on, the request is
     * routed through the approvals engine; otherwise it follows the native path.
     *
     * @param  array<string, mixed>  $meta
     */
    public function requestToJoinFor(
        Model $requester,
        ?string $requestedRole = null,
        ?string $message = null,
        array $meta = [],
        ?CarbonInterface $expiresAt = null,
    ): JoinRequest {
        $request = app(RequestToJoinAction::class)->execute(new RequestToJoinData(
            team: $this->team,
            requester: $requester,
            requestedRole: $requestedRole,
            message: $message,
            meta: $meta,
            expiresAt: $expiresAt,
        ));

        if ($this->shouldRouteThroughApprovals() && $request->isPending()) {
            $request->requestApproval(
                $this->approvalApprovers,
                $this->approvalRule ?? $this->configuredRule(),
                $this->approvalQuorum ?? $this->configuredQuorum(),
            );
        }

        return $request;
    }

    private function shouldRouteThroughApprovals(): bool
    {
        return $this->approvalApprovers !== [] && (bool) config('teams.approvals.enabled', false);
    }

    private function configuredRule(): ApprovalRule
    {
        /** @var string $rule */
        $rule = config('teams.approvals.rule', 'unanimous');

        return ApprovalRule::tryFrom($rule) ?? ApprovalRule::Unanimous;
    }

    private function configuredQuorum(): ?int
    {
        /** @var int|null $quorum */
        $quorum = config('teams.approvals.quorum');

        return $quorum;
    }

    public function transferOwnershipTo(Model $owner): self
    {
        app(TransferOwnershipAction::class)->execute($this->team, $owner);

        return $this;
    }

    /**
     * Typed access to the team's governance settings (join policy, seat cap,
     * default member role, require-approval switch) backed by options.
     */
    public function settings(): TeamSettings
    {
        return new TeamSettings($this->team);
    }

    public function addContactEmail(string $value, ?string $label = null, bool $primary = false): Contact
    {
        return $this->team->addEmail($value, $label, $primary);
    }

    public function addContactPhone(string $value, ?string $label = null, bool $primary = false): Contact
    {
        return $this->team->addPhone($value, $label, $primary);
    }

    public function addContactUrl(string $value, ?string $label = null, bool $primary = false): Contact
    {
        return $this->team->addUrl($value, $label, $primary);
    }

    public function addAddress(
        string $city,
        string $street,
        string $postalCode,
        string $countryIsoCode,
        ?string $name = null,
        bool $isPrimary = false,
        AddressType $type = AddressType::Default,
    ): Address {
        return $this->team->createAddress(
            city: $city,
            street: $street,
            postalCode: $postalCode,
            countryIsoCode: $countryIsoCode,
            name: $name,
            isPrimary: $isPrimary,
            type: $type,
        );
    }

    /**
     * @param  list<string>|null  $permissions
     */
    public function connectTo(Connectable $connectable, ?array $permissions = null, ?CarbonInterface $expiresAt = null): Connection
    {
        return $this->team->connectTo($connectable, $permissions, $expiresAt);
    }

    public function disconnectFrom(Connectable $connectable): self
    {
        $this->team->disconnectFrom($connectable);

        return $this;
    }

    public function team(): Team
    {
        return $this->team;
    }
}
