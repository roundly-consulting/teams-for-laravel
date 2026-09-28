<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Teams\TeamsManager;

final class PruneInvitesCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:invites:prune';

    /** @var string */
    protected $description = 'Delete invites that expired more than a month ago';

    public function handle(TeamsManager $teams): int
    {
        $pruned = $teams->invites()->prune();

        $this->info("Pruned {$pruned} expired invite(s).");

        return self::SUCCESS;
    }
}
