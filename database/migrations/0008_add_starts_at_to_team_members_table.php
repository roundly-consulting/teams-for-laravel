<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membership start dates. Additive and nullable: a null start means the membership is
 * active from the moment it is added, and nothing reads the column while
 * `teams.members.start_dates` is off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table): void {
            $table->timestamp('starts_at')->nullable();
        });
    }
};
