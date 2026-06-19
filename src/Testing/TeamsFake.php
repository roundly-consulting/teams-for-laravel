<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Teams;

/**
 * A recording, still-performing variant of {@see Teams} for host-application
 * tests. Operations run against the database as usual while assertions verify
 * the resulting state, matching Laravel's *::fake() ergonomics.
 */
final class TeamsFake extends Teams
{
    /** @var list<Team> */
    private array $createdTeams = [];

    public function createTeam(CreateTeamData $data): Team
    {
        $team = parent::createTeam($data);

        $this->createdTeams[] = $team;

        return $team;
    }

    public function assertTeamCreated(?string $name = null): void
    {
        if ($name === null) {
            Assert::assertNotEmpty($this->createdTeams, 'Expected a team to be created, but none were.');

            return;
        }

        $names = array_map(static fn (Team $team): string => $team->name, $this->createdTeams);

        Assert::assertContains($name, $names, "Expected a team named [{$name}] to be created.");
    }

    public function assertNothingCreated(): void
    {
        Assert::assertEmpty($this->createdTeams, 'Expected no teams to be created.');
    }

    public function assertMemberAdded(Team $team, Model $member): void
    {
        Assert::assertTrue(
            $team->fresh()?->hasMember($member) ?? false,
            'Expected the member to belong to the team, but it does not.',
        );
    }

    public function assertMemberNotAdded(Team $team, Model $member): void
    {
        Assert::assertFalse(
            $team->fresh()?->hasMember($member) ?? false,
            'Expected the member not to belong to the team, but it does.',
        );
    }

    public function assertInviteCreated(Team $team): void
    {
        Assert::assertTrue(
            $team->invites()->exists(),
            'Expected an invite to exist for the team, but none do.',
        );
    }
}
