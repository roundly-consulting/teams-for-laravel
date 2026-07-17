<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_public')->default(false);
            $table->nullableMorphs('owner');
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
