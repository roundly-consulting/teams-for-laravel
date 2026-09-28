<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\Teams\DataTransferObjects\CreateInviteData;
use RoundlyConsulting\Teams\Events\InviteCreated;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;

final readonly class CreateInviteAction
{
    public function execute(Team $team, CreateInviteData $data): Invite
    {
        /** @var int $codeLength */
        $codeLength = config('teams.invites.code_length', 32);

        $expiresAt = $data->expiresAt
            ?? now()->add(config('teams.invites.expires_after', '7 days'));

        $attributes = [
            'meta' => new Collection($data->meta),
            'code' => Str::random($codeLength),
            'role' => $data->role,
            'email' => $data->email,
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
