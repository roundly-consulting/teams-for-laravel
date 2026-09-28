<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Teams\TeamsManager;

final class PruneMembersCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:members:prune';

    /** @var string */
    protected $description = 'Delete members whose membership expired beyond the configured retention window';

    public function handle(TeamsManager $teams): int
    {
        $pruned = $teams->members()->prune();

        $this->info("Pruned {$pruned} expired member(s).");

        return self::SUCCESS;
    }
}
