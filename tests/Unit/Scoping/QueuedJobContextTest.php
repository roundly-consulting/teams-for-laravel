<?php

declare(strict_types=1);

use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Jobs\SyncJob;
use RoundlyConsulting\Teams\Contracts\WithoutTeamContext;
use RoundlyConsulting\Teams\Scoping\QueuedJobContext;
use RoundlyConsulting\Teams\Scoping\TeamContext;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\ProbeJob;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\SyncSeatsListener;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\TeamArgumentJob;
use RoundlyConsulting\Teams\Tests\User;

it('recognises a marked job, listener, notification and mailable', function (): void {
    $notification = new class extends Notification implements WithoutTeamContext {};
    $mailable = new class extends Mailable implements WithoutTeamContext {};

    expect(QueuedJobContext::isMarked(new TeamArgumentJob(1)))->toBeTrue()
        ->and(QueuedJobContext::isMarked(new CallQueuedListener(SyncSeatsListener::class, 'handle', [])))->toBeTrue()
        ->and(QueuedJobContext::isMarked(new SendQueuedNotifications(new User, $notification)))->toBeTrue()
        ->and(QueuedJobContext::isMarked(new SendQueuedMailable($mailable)))->toBeTrue();
});

it('leaves every other command alone', function (): void {
    expect(QueuedJobContext::isMarked(new ProbeJob))->toBeFalse()
        ->and(QueuedJobContext::isMarked(new CallQueuedListener(stdClass::class, 'handle', [])))->toBeFalse()
        ->and(QueuedJobContext::isMarked(new SendQueuedNotifications(new User, new class extends Notification {})))->toBeFalse()
        ->and(QueuedJobContext::isMarked(new SendQueuedMailable(new class extends Mailable {})))->toBeFalse()
        ->and(QueuedJobContext::isMarked(TeamArgumentJob::class))->toBeFalse()
        ->and(QueuedJobContext::isMarked(null))->toBeFalse();
});

it('adds nothing to a payload it has no team context to strip from', function (array $payload): void {
    expect(app(QueuedJobContext::class)->payload('database', $payload))->toBe([]);
})->with([
    'a string job' => [['job' => 'Handler@handle', 'data' => 'raw']],
    'an unmarked job' => [['data' => ['command' => new ProbeJob], 'illuminate:log:context' => ['data' => [], 'hidden' => [TeamContext::KEYS => 's:0:"";']]]],
    'no context' => [['data' => ['command' => new TeamArgumentJob(1)]]],
    'no hidden context' => [['data' => ['command' => new TeamArgumentJob(1)], 'illuminate:log:context' => ['data' => ['trace' => 's:1:"x";']]]],
    'no team keys' => [['data' => ['command' => new TeamArgumentJob(1)], 'illuminate:log:context' => ['data' => [], 'hidden' => ['other' => 's:1:"x";']]]],
]);

it('resumes nothing for a job it did not suspend', function (): void {
    $before = app(TeamContext::class)->snapshot();

    app(QueuedJobContext::class)->resume(new SyncJob(app(), (string) json_encode(['uuid' => 'unknown']), 'sync', 'default'));
    app(QueuedJobContext::class)->resume(new SyncJob(app(), (string) json_encode(['job' => 'x']), 'sync', 'default'));

    expect(app(TeamContext::class)->snapshot())->toEqual($before);
});
