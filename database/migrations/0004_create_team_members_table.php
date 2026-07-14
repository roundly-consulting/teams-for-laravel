<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->morphs('member');
            $table->string('role')->nullable();
            $table->foreignId('accepted_invite_id')->nullable()->index()
                ->constrained('team_invites')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
