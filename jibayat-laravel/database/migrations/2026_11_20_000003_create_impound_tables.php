<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fourrière : dossiers_fourriere, fou_parametres, fou_types_vehicule,
 * fou_groupes_enchere, fou_vehicules_enchere, fou_ventes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impound_cases', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('registration', 40)->nullable()->index()->comment('immatriculation');
            $table->string('registration_alt', 40)->nullable()->comment('numero_immat');
            $table->string('vehicle_type', 60)->nullable();
            $table->string('deposit_number', 40)->nullable()->index()->comment('num_depot');
            $table->string('depositor', 60)->nullable()->comment('déposant : police, gendarmerie…');
            $table->date('impounded_on')->nullable()->index();
            $table->string('reason')->nullable();
            $table->unsignedInteger('days')->default(0)->comment('nb_jours enregistré (recalculé à l’affichage)');
            $table->decimal('daily_rate', 14, 2)->default(0);
            $table->decimal('towing_fee', 14, 2)->default(0);
            $table->string('owner_name')->nullable();
            $table->string('owner_cin', 30)->nullable()->index();
            $table->string('owner_phone', 40)->nullable();
            $table->string('slip_number', 40)->nullable()->comment('numero_bulletin');
            $table->date('paid_on')->nullable();
            $table->foreignId('payment_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('en_fourriere');
            $table->date('released_on')->nullable()->comment('date_restitution');
            $table->date('release_validated_on')->nullable()->comment('date_sortie_validee');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['is_active', 'impounded_on']);
        });

        Schema::create('impound_settings', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30)->comment('deposant | motif | etat_vehicule | capacite');
            $table->string('code', 60);
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            // Pas d'unicité : la base Flask contient des doublons (ex. capacite/CAPACITE_MAX) conservés à l'import.
            $table->index(['category', 'code']);
        });

        Schema::create('impound_vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->decimal('daily_rate', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('auction_groups', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('label');
            $table->date('auction_on')->nullable();
            $table->string('place')->nullable();
            $table->decimal('opening_price', 14, 2)->default(0);
            $table->string('vehicle_type', 60)->nullable();
            $table->string('status', 20)->default('ouvert');
            $table->text('notes')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('auction_group_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('impound_case_id')->constrained()->cascadeOnDelete();
            $table->string('condition', 20)->default('moyen');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('auction_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('impound_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_cin', 30)->nullable();
            $table->string('buyer_phone', 40)->nullable();
            $table->decimal('hammer_price', 14, 2)->default(0)->comment('prix d’adjudication');
            $table->string('receipt_number', 40)->nullable()->index();
            $table->date('sold_on')->nullable();
            $table->string('report_path')->nullable()->comment('PV de vente');
            $table->string('report_name')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['auction_sales', 'auction_group_vehicles', 'auction_groups', 'impound_vehicle_types', 'impound_settings', 'impound_cases'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
