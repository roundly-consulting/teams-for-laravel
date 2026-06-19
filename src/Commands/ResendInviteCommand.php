<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Teams\Actions\ResendInviteAction;
use RoundlyConsulting\Teams\Models\Invite;

final class ResendInviteCommand extends Command
{
    /** @var string */
    protected $signature = 'teams:invites:resend {code}';

    /** @var string */
    protected $description = 'Resend an invite, rotating its code and extending its expiry';

    public function handle(ResendInviteAction $action): int
    {
        /** @var string $code */
        $code = $this->argument('code');

        /** @var class-string<Invite> $model */
        $model = config('teams.models.invite', Invite::class);

        /** @var Invite|null $invite */
        $invite = $model::query()->where('code', $code)->first();

        if ($invite === null) {
            $this->error("No invite found for code \"{$code}\".");

            return self::FAILURE;
        }

        $invite = $action->execute($invite);

        $this->info("Invite resent. New code: {$invite->code}");

        return self::SUCCESS;
    }
}
