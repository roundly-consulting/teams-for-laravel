<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RoundlyConsulting\Teams\Events\InviteCreated;
use RoundlyConsulting\Teams\Events\TeamMemberAdded;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Tests\User;

final class MemberWelcomeNotification extends Notification
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->line('Welcome to the team.');
    }
}

final class TestTeamSubscriber implements ShouldQueue
{
    public function handleMemberAdded(TeamMemberAdded $event): void
    {
        NotificationFacade::send($event->member->member, new MemberWelcomeNotification);
    }

    public function handleInviteCreated(InviteCreated $event): void
    {
        if ($event->invite->email !== null) {
            NotificationFacade::route('mail', $event->invite->email)
                ->notify(new MemberWelcomeNotification);
        }
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            TeamMemberAdded::class => 'handleMemberAdded',
            InviteCreated::class => 'handleInviteCreated',
        ];
    }
}

it('turns team events into notifications through a subscriber', function (): void {
    NotificationFacade::fake();
    Event::subscribe(TestTeamSubscriber::class);

    $team = Team::factory()->create();
    $user = User::create();

    $team->addMember($user, 'admin');

    NotificationFacade::assertSentTo($user, MemberWelcomeNotification::class);
});

it('notifies an invited email when an invite is created', function (): void {
    NotificationFacade::fake();
    Event::subscribe(TestTeamSubscriber::class);

    $team = Team::factory()->create();
    Team::factory()->create();

    $team->invite(now()->addWeek(), 'admin');
    $invite = $team->invites()->create([
        'code' => 'with-email',
        'role' => 'admin',
        'email' => 'jane@acme.test',
        'expires_at' => now()->addWeek(),
        'uses' => 0,
        'max_uses' => 1,
    ]);

    InviteCreated::dispatch($invite);

    NotificationFacade::assertSentOnDemand(MemberWelcomeNotification::class);
});

it('ships an event subscriber stub', function (): void {
    $stub = file_get_contents(__DIR__.'/../../stubs/TeamEventSubscriber.stub');

    expect($stub)->toContain('class TeamEventSubscriber implements ShouldQueue')
        ->and($stub)->toContain('subscribe(Dispatcher $events)');
});
