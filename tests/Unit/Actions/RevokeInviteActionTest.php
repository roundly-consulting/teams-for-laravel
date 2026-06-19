<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Teams\Actions\RevokeInviteAction;
use RoundlyConsulting\Teams\Events\InviteRevoked;
use RoundlyConsulting\Teams\Models\Invite;

it('revokes an invite and dispatches the event', function () {
    Event::fake(InviteRevoked::class);

    $invite = Invite::factory()->create();

    expect(app(RevokeInviteAction::class)->execute($invite))->toBeTrue();

    $this->assertSoftDeleted($invite);
    Event::assertDispatched(fn (InviteRevoked $e) => $e->invite->is($invite));
});
