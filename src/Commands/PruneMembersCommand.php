<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Teams\Actions\PruneExpiredMembersAction;

final class PruneMembersCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:members:prune';

    /** @var string */
    protected $description = 'Delete members whose membership expired beyond the configured retention window';

    public function handle(PruneExpiredMembersAction $action): int
    {
        $pruned = $action->execute();

        $this->info("Pruned {$pruned} expired member(s).");

        return self::SUCCESS;
    }
}
