<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Host tables for the team-scoping suite, built the way a host would: `teamScope()`.
 * Runs after the package's own migrations (the fixture directory is the last source).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->teamScope();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->teamScope()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects');
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->teamScope('company_id');
            $table->string('title');
            $table->timestamps();
        });

        // Another package's table: the team column and index the host added itself.
        Schema::create('vendor_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->index();
            $table->string('subject');
            $table->timestamps();
        });

        Schema::create('unindexed_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('team_id');
        });

        Schema::create('columnless_notes', function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }
};
