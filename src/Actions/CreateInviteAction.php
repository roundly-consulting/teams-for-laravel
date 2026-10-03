<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\Teams\DataTransferObjects\CreateInviteData;
use RoundlyConsulting\Teams\Events\InviteCreated;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Support\TeamsConfig;

final readonly class CreateInviteAction
{
    public function execute(Team $team, CreateInviteData $data): Invite
    {
        $codeLength = TeamsConfig::inviteCodeLength();

        $expiresAt = $data->expiresAt ?? now()->add(TeamsConfig::inviteExpiresAfter());

        $attributes = [
            'meta' => new Collection($data->meta),
            'code' => Str::random($codeLength),
            'role' => $data->role,
            // Stored lower-cased: invite emails match case-insensitively.
            'email' => $data->email !== null ? Str::lower(trim($data->email)) : null,
            'expires_at' => $expiresAt,
            'uses' => 0,
            'max_uses' => $data->maxUses,
        ];

        if ($data->invitedBy !== null) {
            $attributes['invited_by_type'] = $data->invitedBy->getMorphClass();
            $attributes['invited_by_id'] = $data->invitedBy->getKey();
        }

        /** @var Invite $invite */
        $invite = $team->invites()->create($attributes);

        InviteCreated::dispatch($invite);

        return $invite;
    }
}
