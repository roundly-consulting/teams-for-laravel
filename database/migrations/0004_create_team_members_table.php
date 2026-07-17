<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $keyType = KeyType::fromConfig('teams.key_type');

        Schema::create('team_members', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->morphKey('member', $keyType, nullable: false);
            $table->string('role')->nullable();
            $table->foreignId('accepted_invite_id')->nullable()->index()
                ->constrained('team_invites')->nullOnDelete();
            $table->jsonb('meta')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
