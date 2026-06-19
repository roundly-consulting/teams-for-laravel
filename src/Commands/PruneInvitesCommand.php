<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Teams\Actions\PruneInvitesAction;

final class PruneInvitesCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:invites:prune';

    /** @var string */
    protected $description = 'Delete invites that expired more than a month ago';

    public function handle(PruneInvitesAction $action): int
    {
        $pruned = $action->execute();

        $this->info("Pruned {$pruned} expired invite(s).");

        return self::SUCCESS;
    }
}
