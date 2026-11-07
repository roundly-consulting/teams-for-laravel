<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Teams\Actions\MoveTeamAction;
use RoundlyConsulting\Teams\Events\TeamMoved;
use RoundlyConsulting\Teams\Exceptions\TeamHierarchyException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Policies\AbstractTeamPolicy;
use RoundlyConsulting\Teams\TeamsManager;
use RoundlyConsulting\Teams\Tests\User;

/**
 * company → department → squad
 *
 * @return array{0: Team, 1: Team, 2: Team}
 */
function companyTree(): array
{
    $company = Team::factory()->create(['name' => 'Company']);
    $department = Team::factory()->create(['name' => 'Department']);
    $squad = Team::factory()->create(['name' => 'Squad']);

    Teams::for($department)->moveUnder($company);
    Teams::for($squad)->moveUnder($department);

    return [$company->fresh(), $department->fresh(), $squad->fresh()];
}

describe('with nesting off (the default)', function (): void {
    it('refuses a parent and reads as a flat team', function (): void {
        $company = Team::factory()->create();
        $team = Team::factory()->create();

        expect(fn () => Teams::for($team)->moveUnder($company))->toThrow(TeamHierarchyException::class, 'Nested teams are disabled.')
            ->and(fn () => $team->update(['parent_id' => $company->getKey()]))->toThrow(TeamHierarchyException::class);

        expect($team->fresh()->parent_id)->toBeNull()
            ->and($team->root()->is($team))->toBeTrue()
            ->and($team->ancestors())->toBeEmpty()
            ->and($company->descendants())->toBeEmpty()
            ->and($team->depth())->toBe(0);
    });

    it('still lets a team be detached', function (): void {
        $team = Team::factory()->create();

        expect(Teams::for($team)->moveToRoot()->parent_id)->toBeNull();
    });
});

describe('with nesting on', function (): void {
    beforeEach(function (): void {
        config()->set('teams.nesting.enabled', true);
    });

    it('walks the tree', function (): void {
        [$company, $department, $squad] = companyTree();

        expect($squad->root()->is($company))->toBeTrue()
            ->and($squad->ancestors()->modelKeys())->toBe([$department->getKey(), $company->getKey()])
            ->and($company->descendants()->modelKeys())->toBe([$department->getKey(), $squad->getKey()])
            ->and($squad->depth())->toBe(2)
            ->and($company->depth())->toBe(0)
            ->and($company->root()->is($company))->toBeTrue()
            ->and($department->parent?->is($company))->toBeTrue()
            ->and($company->children->modelKeys())->toBe([$department->getKey()]);
    });

    it('rejects a cycle', function (): void {
        [$company, $department, $squad] = companyTree();

        expect(fn () => Teams::for($company)->moveUnder($squad))->toThrow(TeamHierarchyException::class, 'cannot be placed under itself')
            ->and(fn () => Teams::for($company)->moveUnder($company))->toThrow(TeamHierarchyException::class)
            ->and(fn () => $department->update(['parent_id' => $squad->getKey()]))->toThrow(TeamHierarchyException::class);

        expect($company->fresh()->parent_id)->toBeNull();
    });

    it('rejects a cycle through a soft-deleted team', function (): void {
        [$company, $department, $squad] = companyTree();
        $department->delete();

        expect(fn () => Teams::for($company)->moveUnder($squad))->toThrow(TeamHierarchyException::class);
    });

    it('enforces the depth limit, counting the subtree being moved', function (): void {
        config()->set('teams.nesting.max_depth', 2);
        [$company, $department, $squad] = companyTree();
        $tiny = Team::factory()->create();

        expect(fn () => Teams::for($tiny)->moveUnder($squad))->toThrow(TeamHierarchyException::class, 'at most 2 levels');

        // Moving a two-level subtree under a root would put its leaf at depth 3.
        $holding = Team::factory()->create();
        Teams::for($holding)->moveUnder(Team::factory()->create());

        expect(fn () => Teams::for($company)->moveUnder($holding))->toThrow(TeamHierarchyException::class);

        Teams::for($tiny)->moveUnder($department);

        expect($tiny->fresh()->depth())->toBe(2);
    });

    it('translates the depth message with plural forms', function (): void {
        app()->setLocale('sk');

        expect(TeamHierarchyException::tooDeep(1)->getMessage())->toContain('1 úroveň')
            ->and(TeamHierarchyException::tooDeep(3)->getMessage())->toContain('3 úrovne')
            ->and(TeamHierarchyException::tooDeep(5)->getMessage())->toContain('5 úrovní');
    });

    it('rejects an out-of-range max depth', function (): void {
        config()->set('teams.nesting.max_depth', 0);

        expect(fn () => Teams::for(Team::factory()->create())->moveUnder(Team::factory()->create()))->toThrow(InvalidConfigurationException::class);
    });

    it('moves through the facade, the injected manager and the raw action alike', function (): void {
        Event::fake([TeamMoved::class]);
        $company = Team::factory()->create();
        $other = Team::factory()->create();
        $team = Team::factory()->create();

        Teams::for($team)->moveUnder($company);
        app(TeamsManager::class)->for($team)->moveUnder($other);
        app(MoveTeamAction::class)->execute($team, null);

        expect($team->fresh()->parent_id)->toBeNull();

        Event::assertDispatched(TeamMoved::class, fn (TeamMoved $event): bool => $event->parent?->is($other) === true && $event->previousParentKey === $company->getKey());
        Event::assertDispatched(TeamMoved::class, fn (TeamMoved $event): bool => $event->parent === null && $event->previousParentKey === $other->getKey());
    });

    it('stops ancestors at a soft-deleted team', function (): void {
        [$company, $department, $squad] = companyTree();
        $department->delete();

        expect($squad->ancestors())->toBeEmpty()
            ->and($squad->root()->is($squad))->toBeTrue();
    });

    it('keeps membership per team', function (): void {
        [$company, $department] = companyTree();
        $user = User::create();
        Teams::for($company)->members()->add($user, 'admin');

        expect($department->hasMember($user))->toBeFalse()
            ->and($user->belongsToTeam($department))->toBeFalse();
    });
});

describe('role inheritance', function (): void {
    beforeEach(function (): void {
        config()->set('teams.nesting.enabled', true);
        Teams::roles()->register('editor', 'Editor', ['posts.edit']);
    });

    it('is off by default: a parent role grants nothing below', function (): void {
        [$company, $department, $squad] = companyTree();
        $user = User::create();
        Teams::for($company)->members()->add($user, 'editor');

        expect($squad->memberHasPermission($user, 'posts.edit'))->toBeFalse()
            ->and($squad->memberHasRole($user, 'editor'))->toBeFalse()
            ->and($squad->isAccessibleBy($user))->toBeFalse()
            ->and($user->can('teams.posts.edit', $squad))->toBeFalse()
            ->and($company->memberHasPermission($user, 'posts.edit'))->toBeTrue();
    });

    it('applies a parent role on every team below when on', function (): void {
        config()->set('teams.nesting.inherit_roles_down', true);
        [$company, $department, $squad] = companyTree();
        $user = User::create();
        Teams::for($company)->members()->add($user, 'editor');

        expect($squad->memberHasPermission($user, 'posts.edit'))->toBeTrue()
            ->and($squad->memberHasPermission($user, 'posts.delete'))->toBeFalse()
            ->and($squad->memberHasRole($user, 'editor'))->toBeTrue()
            ->and($squad->isAccessibleBy($user))->toBeTrue()
            ->and($user->can('teams.posts.edit', $squad))->toBeTrue()
            ->and($user->hasTeamPermission($department, 'posts.edit'))->toBeTrue()
            // ...but never upward.
            ->and(Team::factory()->create()->memberHasPermission($user, 'posts.edit'))->toBeFalse();

        $squadOnly = User::create();
        Teams::for($squad)->members()->add($squadOnly, 'editor');

        expect($company->memberHasPermission($squadOnly, 'posts.edit'))->toBeFalse()
            ->and($company->isAccessibleBy($squadOnly))->toBeFalse();
    });

    it('honours it in a team policy', function (): void {
        config()->set('teams.nesting.inherit_roles_down', true);
        [$company, , $squad] = companyTree();
        $user = User::create();
        Teams::for($company)->members()->add($user, 'editor');

        $policy = new class extends AbstractTeamPolicy
        {
            public function update(User $user, Team $team): bool
            {
                return $this->allows($user, $team, 'posts.edit');
            }
        };

        expect($policy->update($user, $squad))->toBeTrue();
    });

    it('ignores an expired parent membership', function (): void {
        config()->set('teams.nesting.inherit_roles_down', true);
        [$company, , $squad] = companyTree();
        $user = User::create();
        Teams::for($company)->members()->add($user, 'editor', expiresAt: now()->subMinute());

        expect($squad->memberHasPermission($user, 'posts.edit'))->toBeFalse()
            ->and($squad->isAccessibleBy($user))->toBeFalse();
    });

    it('has no effect while nesting itself is off', function (): void {
        config()->set('teams.nesting.inherit_roles_down', true);
        [$company, , $squad] = companyTree();
        config()->set('teams.nesting.enabled', false);
        $user = User::create();
        Teams::for($company)->members()->add($user, 'editor');

        expect($squad->memberHasPermission($user, 'posts.edit'))->toBeFalse();
    });
});
