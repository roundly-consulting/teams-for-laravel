<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Policies\AbstractTeamPolicy;
use RoundlyConsulting\Teams\Tests\User;

beforeEach(fn () => Teams::roles()->register('editor', 'Editor', ['posts.edit']));

function teamPolicy(): object
{
    return new class extends AbstractTeamPolicy
    {
        public function update(Model $user, Team $team): bool
        {
            return $this->allows($user, $team, 'posts.edit');
        }
    };
}

it('grants every ability to the owner via before()', function (): void {
    $owner = User::create();
    $team = Team::factory()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    expect(teamPolicy()->before($owner, 'update', $team))->toBeTrue();
});

it('falls through before() for a non-owner', function (): void {
    $user = User::create();
    $team = Team::factory()->create();

    expect(teamPolicy()->before($user, 'update', $team))->toBeNull();
});

it('authorizes a permission through allows()', function (): void {
    $team = Team::factory()->create();
    $editor = User::create();
    $viewer = User::create();
    $team->addMember($editor, 'editor');
    $team->addMember($viewer, 'user');

    expect(teamPolicy()->update($editor, $team))->toBeTrue()
        ->and(teamPolicy()->update($viewer, $team))->toBeFalse();
});

it('lets class-level abilities through before() to the policy method', function (): void {
    $policy = new class extends AbstractTeamPolicy
    {
        public function create(Model $user): bool
        {
            return true;
        }

        public function viewAny(Model $user): bool
        {
            return false;
        }
    };

    Gate::policy(Team::class, $policy::class);
    $user = User::create();

    expect($user->can('create', Team::class))->toBeTrue()
        ->and($user->can('viewAny', Team::class))->toBeFalse()
        ->and($policy->before($user, 'create', Team::class))->toBeNull()
        ->and($policy->before($user, 'create'))->toBeNull();
});
