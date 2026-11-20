<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Détails de copropriété que l'écran Flask saisit (type de titre, date d'entrée, acte, notes)
 * mais que la table SQLite ne possédait pas (l'ajout échouait dans Flask).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_co_owners', function (Blueprint $table) {
            $table->string('title_type', 30)->default('indivision')->after('share_percent');
            $table->date('joined_on')->nullable()->after('title_type');
            $table->string('notarial_deed')->nullable()->after('joined_on');
            $table->text('notes')->nullable()->after('notarial_deed');
        });
    }

    public function down(): void
    {
        Schema::table('parcel_co_owners', function (Blueprint $table) {
            $table->dropColumn(['title_type', 'joined_on', 'notarial_deed', 'notes']);
        });
    }
};
