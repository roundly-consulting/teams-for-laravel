<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Teams\Actions\ExpireJoinRequestsAction;

final class PruneJoinRequestsCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:join-requests:prune';

    /** @var string */
    protected $description = 'Auto-decline pending join requests whose expiry has passed';

    public function handle(ExpireJoinRequestsAction $action): int
    {
        $declined = $action->execute();

        $this->info("Auto-declined {$declined} expired join request(s).");

        return self::SUCCESS;
    }
}
