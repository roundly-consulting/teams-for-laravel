<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\TeamsManager;

final class ResendInviteCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:invites:resend {code}';

    /** @var string */
    protected $description = 'Resend an invite, rotating its code and extending its expiry';

    public function handle(TeamsManager $teams): int
    {
        /** @var string $code */
        $code = $this->argument('code');

        $invite = $teams->invites()->find($code);

        /** @var Team|null $team */
        $team = $invite?->team;

        if ($invite === null || $team === null) {
            $this->error("No invite found for code \"{$code}\".");

            return self::FAILURE;
        }

        $invite = $teams->for($team)->invites()->resend($invite);

        $this->info("Invite resent. New code: {$invite->code}");

        return self::SUCCESS;
    }
}
