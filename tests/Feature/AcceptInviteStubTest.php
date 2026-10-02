<?php

declare(strict_types=1);

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Teams\Exceptions\InviteEmailMismatchException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\Fixtures\VerifiableUser;
use RoundlyConsulting\Teams\Tests\User;

/**
 * Load the two published stubs exactly as a host would: the controller into
 * App\Http\Controllers (on top of the skeleton's base Controller) and the route file.
 */
function loadAcceptInviteStubs(): void
{
    // The "web" group encrypts its cookies.
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));

    if (! class_exists('App\Http\Controllers\Controller')) {
        class_alias(Controller::class, 'App\Http\Controllers\Controller');
    }

    if (! class_exists('App\Http\Controllers\AcceptInviteController')) {
        require __DIR__.'/../../stubs/AcceptInviteController.stub';
    }

    Route::get('teams/{team}', fn (string $team): string => 'team '.$team)->name('teams.show');

    require __DIR__.'/../../stubs/teams-routes.stub';

    Route::getRoutes()->refreshNameLookups();
}

function verifiedUser(string $email): VerifiableUser
{
    return VerifiableUser::query()->create(['email' => $email, 'email_verified_at' => now()]);
}

it('publishes the accept-invite stubs', function (): void {
    $controller = file_get_contents(__DIR__.'/../../stubs/AcceptInviteController.stub');
    $routes = file_get_contents(__DIR__.'/../../stubs/teams-routes.stub');

    expect($controller)->toContain('class AcceptInviteController')
        ->and($routes)->toContain('teams/invites/{invite}');
});

it('never accepts an invite on GET — it only shows a confirmation form', function (): void {
    loadAcceptInviteStubs();
    $team = Team::factory()->create(['name' => 'Acme']);
    $invite = Teams::for($team)->invites()->create(role: 'admin');
    $user = verifiedUser('jane@acme.test');

    $this->actingAs($user)
        ->get('teams/invites/'.$invite->code)
        ->assertOk()
        ->assertSee('Join Acme as admin?')
        ->assertSee('method="POST"', escape: false);

    expect($team->hasMember($user))->toBeFalse()
        ->and(Route::getRoutes()->getByName('teams.invites.accept')?->methods())->toBe(['POST'])
        ->and(Route::getRoutes()->getByName('teams.invites.accept')?->gatherMiddleware())->toContain('web', 'auth', 'verified');
});

it('accepts the invite on POST for a verified email', function (): void {
    loadAcceptInviteStubs();
    $team = Team::factory()->create();
    $invite = Teams::for($team)->invites()->create(role: 'admin', email: 'jane@acme.test');
    $user = verifiedUser('Jane@Acme.test');

    $this->actingAs($user)
        ->post('teams/invites/'.$invite->code)
        ->assertRedirect('teams/'.$team->getKey());

    expect($team->findMember($user)?->role)->toBe('admin');
});

it('keeps an unverified account away from the invite', function (): void {
    loadAcceptInviteStubs();
    $team = Team::factory()->create();
    $invite = Teams::for($team)->invites()->create(role: 'admin', email: 'jane@acme.test');
    $user = VerifiableUser::query()->create(['email' => 'jane@acme.test']);

    $this->actingAs($user)
        ->postJson('teams/invites/'.$invite->code)
        ->assertForbidden();

    expect($team->hasMember($user))->toBeFalse();
});

it('offers no email to an email-targeted invite when the account cannot prove one', function (): void {
    loadAcceptInviteStubs();
    $team = Team::factory()->create();
    $invite = Teams::for($team)->invites()->create(role: 'admin', email: 'jane@acme.test');
    $user = User::create();

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($user)->post('teams/invites/'.$invite->code))
        ->toThrow(InviteEmailMismatchException::class);

    expect($team->hasMember($user))->toBeFalse();
});

it('accepts an invite through a route bound on its code', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    Invite::factory()->for($team)->create(['code' => 'route-code', 'role' => 'admin']);

    Route::post('teams/accept/{code}', function (string $code) use ($user): string {
        Teams::invites()->accept($code, $user);

        return 'accepted';
    });

    $this->post('teams/accept/route-code')->assertOk()->assertSee('accepted');

    expect($team->hasMember($user))->toBeTrue();
});
