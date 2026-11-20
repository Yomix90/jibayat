<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif choisi pour l'établissement. Flask enregistrait le code du tarif dans type_etablissement puis retrouvait le taux
 * par mots-clés ; les tarifs réels n'ont pas de code (« None » enregistré) et tous les établissements prenaient le taux
 * le plus bas. Le tarif est désormais lié directement ; sans tarif lié, la règle Flask par mots-clés s'applique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beverage_outlets', function (Blueprint $table) {
            $table->foreignId('tariff_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('beverage_outlets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tariff_id');
        });
    }
};
