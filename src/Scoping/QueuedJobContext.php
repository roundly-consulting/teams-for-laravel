<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SyncQueue;
use InvalidArgumentException;
use RoundlyConsulting\Teams\Contracts\WithoutTeamContext;

/**
 * Keeps the team context out of the payload of a job marked {@see WithoutTeamContext}.
 *
 * Laravel's own payload hook has already dehydrated the whole Context into
 * `illuminate:log:context` by the time this one runs (it is registered after every
 * provider booted), so the three team keys are stripped from its hidden half. The worker's
 * hydration then restores everything else and no team.
 *
 * The sync queue runs the job in the dispatching process, and its hydration FLUSHES the
 * dispatcher's Context and replaces it with the job's. Laravel puts nothing back afterwards:
 * for an ordinary job the payload holds the dispatcher's own context, so nothing is lost —
 * but a stripped payload would leave the dispatcher without its team once the job returns.
 * So for a sync connection the dispatcher's team context is kept here, by job uuid, and put
 * back when the job has been attempted (it ran, failed or threw).
 *
 * @internal
 */
final class QueuedJobContext
{
    public const string PAYLOAD_KEY = 'illuminate:log:context';

    /** @var array<string, ContextSnapshot> */
    private array $suspended = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * The `Queue::createPayloadUsing()` hook: the rewritten context for a marked job,
     * nothing for any other.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    public function payload(?string $connection, array $payload): array
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data) || ! self::isMarked($data['command'] ?? null)) {
            return [];
        }

        $context = $payload[self::PAYLOAD_KEY] ?? null;

        if (! is_array($context) || ! is_array($context['hidden'] ?? null)) {
            return [];
        }

        $hidden = $context['hidden'];
        $stripped = array_diff_key($hidden, array_flip([TeamContext::KEYS, TeamContext::MULTIPLE, TeamContext::ACTOR]));

        if (count($stripped) === count($hidden)) {
            return [];
        }

        $this->suspendForSyncRun($connection, $payload['uuid'] ?? null);

        $context['hidden'] = $stripped;

        // An empty context is dehydrated as null by Laravel itself; hydrate(null) flushes.
        $empty = $stripped === [] && (! is_array($context['data'] ?? null) || $context['data'] === []);

        return [self::PAYLOAD_KEY => $empty ? null : $context];
    }

    /**
     * Put the dispatcher's team context back once a sync-run marked job is over.
     */
    public function resume(Job $job): void
    {
        if (! $job instanceof SyncJob) {
            return;
        }

        $uuid = $job->uuid();

        if ($uuid === null || ! isset($this->suspended[$uuid])) {
            return;
        }

        $snapshot = $this->suspended[$uuid];
        unset($this->suspended[$uuid]);

        $this->container->make(TeamContext::class)->restore($snapshot);
    }

    /**
     * Whether a queued command opted out of the team context: the job itself, or the
     * listener, notification or mailable a framework wrapper queues.
     */
    public static function isMarked(mixed $command): bool
    {
        if ($command instanceof WithoutTeamContext) {
            return true;
        }

        return match (true) {
            $command instanceof CallQueuedListener => is_a($command->class, WithoutTeamContext::class, true),
            $command instanceof SendQueuedNotifications => $command->notification instanceof WithoutTeamContext,
            $command instanceof SendQueuedMailable => $command->mailable instanceof WithoutTeamContext,
            default => false,
        };
    }

    private function suspendForSyncRun(?string $connection, mixed $uuid): void
    {
        if ($connection === null || ! is_string($uuid) || ! $this->runsInProcess($connection)) {
            return;
        }

        $this->suspended[$uuid] = $this->container->make(TeamContext::class)->snapshot();
    }

    /**
     * The sync family (sync, deferred, background's inner sync run) executes the job right
     * here; every other connection hands it to a worker.
     */
    private function runsInProcess(string $connection): bool
    {
        try {
            return $this->container->make(QueueFactory::class)->connection($connection) instanceof SyncQueue;
        } catch (InvalidArgumentException) {
            // A queue built outside the manager, under a name it does not know: not sync.
            return false;
        }
    }
}
