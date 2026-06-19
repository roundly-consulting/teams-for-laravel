<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_role_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('key');
            $table->string('name');
            $table->json('permissions')->nullable();
            $table->string('description')->default('');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'key']);
        });
    }
};
