<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Teams\Support\TeamsSchema;

/**
 * Nested teams (company → department → team). Additive and nullable: a flat team keeps a
 * null parent, and nothing reads the column while `teams.nesting.enabled` is off.
 *
 * No delete action: force-deleting a team that still has child teams fails rather than
 * silently turning its children into roots — move or delete them first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            TeamsSchema::teamForeignKey($table, 'parent_id')->nullable()->index()->constrained('teams');
        });
    }
};
