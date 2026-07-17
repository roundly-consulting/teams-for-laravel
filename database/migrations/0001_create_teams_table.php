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

        Schema::create('teams', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_public')->default(false);
            $table->morphKey('owner', $keyType, nullable: true);
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
