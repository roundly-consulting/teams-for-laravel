<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();

            // Deliberately unconstrained: this is a host's users table, and the column
            // exists so the "current team" reader trait has something to read. A real host
            // would not necessarily constrain it either.
            $table->foreignId('team_id')->nullable();
        });
    }
};
