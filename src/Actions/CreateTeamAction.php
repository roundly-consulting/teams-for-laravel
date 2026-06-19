<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Actions;

use Illuminate\Support\Collection;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Models\Team;

final class CreateTeamAction
{
    public function __construct(
        private readonly AddMemberAction $addMember,
    ) {}

    public function execute(CreateTeamData $data): Team
    {
        /** @var class-string<Team> $model */
        $model = config('teams.models.team', Team::class);

        $attributes = [
            'name' => $data->name,
            'is_public' => $data->isPublic,
            'meta' => new Collection($data->meta),
        ];

        if ($data->owner !== null) {
            $attributes['owner_type'] = $data->owner->getMorphClass();
            $attributes['owner_id'] = $data->owner->getKey();
        }

        /** @var Team $team */
        $team = $model::query()->create($attributes);

        if ($data->owner !== null) {
            /** @var string $ownerRole */
            $ownerRole = config('teams.roles.owner', 'owner');

            $this->addMember->execute($team, new AddMemberData(
                member: $data->owner,
                role: $ownerRole,
            ));
        }

        return $team;
    }
}
