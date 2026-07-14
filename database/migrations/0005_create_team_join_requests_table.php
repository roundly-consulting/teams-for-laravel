<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_join_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->morphs('requester');
            $table->string('requested_role')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->nullableMorphs('responded_by');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
