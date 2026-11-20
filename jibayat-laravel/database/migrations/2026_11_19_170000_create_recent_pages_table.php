<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des pages consultées par utilisateur (puces « RÉCENTS » de l'accueil).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recent_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('route_name', 120);
            $table->unsignedInteger('visits')->default(1);
            $table->timestamp('visited_at');
            $table->unique(['user_id', 'route_name']);
            $table->index(['user_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recent_pages');
    }
};
