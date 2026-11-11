<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Teams\Support\TeamsSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_role_overrides', function (Blueprint $table): void {
            $table->id();
            TeamsSchema::teamForeignKey($table, 'team_id')->constrained('teams')->onDelete('cascade');
            $table->string('key');
            $table->string('name');
            $table->jsonb('permissions')->nullable();
            $table->string('description')->default('');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'key']);
        });
    }
};
