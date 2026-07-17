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

        Schema::create('team_invites', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('role');
            $table->string('email')->nullable()->index();
            $table->morphKey('invited_by', $keyType, nullable: true);
            $table->jsonb('meta')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
