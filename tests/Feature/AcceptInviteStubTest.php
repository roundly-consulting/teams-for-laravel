<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Invite;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

it('publishes the accept-invite stubs', function (): void {
    $controller = file_get_contents(__DIR__.'/../../stubs/AcceptInviteController.stub');
    $routes = file_get_contents(__DIR__.'/../../stubs/teams-routes.stub');

    expect($controller)->toContain('class AcceptInviteController')
        ->and($routes)->toContain('teams/invites/{invite}');
});

it('accepts an invite through a route bound on its code', function (): void {
    $team = Team::factory()->create();
    $user = User::create();
    Invite::factory()->for($team)->create(['code' => 'route-code', 'role' => 'admin']);

    Route::get('teams/invites/{code}', function (string $code) use ($user): string {
        Teams::acceptInviteByCode($code, $user);

        return 'accepted';
    });

    $this->get('teams/invites/route-code')->assertOk()->assertSee('accepted');

    expect($team->hasMember($user))->toBeTrue();
});
