<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\JoinRequest;
use RoundlyConsulting\Teams\Models\Member;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Models\TeamRole;
use RoundlyConsulting\Teams\Support\InviteModel;
use RoundlyConsulting\Teams\Support\JoinRequestModel;
use RoundlyConsulting\Teams\Support\MemberModel;
use RoundlyConsulting\Teams\Support\TeamModel;
use RoundlyConsulting\Teams\Support\TeamRoleModel;
use RoundlyConsulting\Teams\Tests\Fixtures\CustomTeam;

/**
 * The five `teams.models.*` keys are the package's documented model-swap seam.
 * Every one resolves through the toolkit's ModelResolver, so the contract is the
 * same at all five: the packaged model by default, a host subclass when
 * configured, the packaged model back when the configured model is real but not
 * ours, and a hard failure when it is not a model at all.
 */
dataset('resolvers', [
    'team' => [TeamModel::class, 'teams.models.team', Team::class],
    'member' => [MemberModel::class, 'teams.models.member', Member::class],
    'invite' => [InviteModel::class, 'teams.models.invite', Invite::class],
    'team role' => [TeamRoleModel::class, 'teams.models.team_role', TeamRole::class],
    'join request' => [JoinRequestModel::class, 'teams.models.join_request', JoinRequest::class],
]);

it('resolves the packaged model by default', function (string $resolver, string $key, string $model): void {
    expect($resolver::class())->toBe($model)
        ->and($resolver::new())->toBeInstanceOf($model);
})->with('resolvers');

it('throws when the configured value is not an Eloquent model', function (string $resolver, string $key): void {
    config()->set($key, 'Not\\A\\Model');

    $resolver::class();
})->with('resolvers')->throws(InvalidConfigurationException::class);

it('falls back to the packaged model when the configured model is not ours', function (string $resolver, string $key, string $model): void {
    // A real Eloquent model — the toolkit validates is-a-Model, never is-a-*ours* —
    // that cannot answer this package's casts, scopes and relations.
    config()->set($key, ForeignModel::class);

    expect($resolver::class())->toBe($model);
})->with('resolvers');

it('honours a host subclass of the packaged model', function (): void {
    config()->set('teams.models.team', CustomTeam::class);

    expect(TeamModel::class())->toBe(CustomTeam::class)
        ->and(TeamModel::new())->toBeInstanceOf(CustomTeam::class)
        ->and(TeamModel::query()->getModel())->toBeInstanceOf(CustomTeam::class);
});

final class ForeignModel extends Model
{
    protected $table = 'foreign_models';
}
