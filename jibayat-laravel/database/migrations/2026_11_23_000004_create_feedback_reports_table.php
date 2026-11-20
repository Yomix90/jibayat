<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** « Signaler un problème » : signalements et suggestions des utilisateurs, suivis par l'administrateur. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('probleme')->comment('probleme | suggestion | question');
            $table->string('module', 60)->nullable();
            $table->string('page_url')->nullable();
            $table->text('message');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('status', 20)->default('nouveau')->comment('nouveau | en_cours | traite');
            $table->text('answer')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_reports');
    }
};
