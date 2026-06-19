<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('role');
            $table->json('meta')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
