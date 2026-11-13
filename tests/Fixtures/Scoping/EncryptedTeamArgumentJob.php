<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use RoundlyConsulting\Teams\Contracts\WithoutTeamContext;

/**
 * The marked platform job, encrypted on the queue: its command is ciphertext, so the
 * marker must be read before Laravel encrypts it.
 */
final class EncryptedTeamArgumentJob implements ShouldBeEncrypted, ShouldQueue, WithoutTeamContext
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public int|string $teamId,
    ) {}

    public function handle(): void
    {
        TeamArgumentJob::record($this->teamId);
    }
}
