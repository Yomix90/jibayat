<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Préférences e-mail : alertes par e-mail et fréquence du rapport de recettes (aucun | quotidien | hebdomadaire | mensuel). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_alerts')->default(false)->after('email');
            $table->string('email_report', 12)->default('aucun')->after('email_alerts');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['email_alerts', 'email_report']));
    }
};
