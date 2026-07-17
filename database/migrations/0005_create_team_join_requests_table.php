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

        Schema::create('team_join_requests', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->morphKey('requester', $keyType, nullable: false);
            $table->string('requested_role')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('message')->nullable();
            $table->jsonb('meta')->nullable();
            $table->morphKey('responded_by', $keyType, nullable: true);
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
