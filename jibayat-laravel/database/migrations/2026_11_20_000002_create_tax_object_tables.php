<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Objets taxables par module.
 * TNB      : dossiers_tnb, terrains, permis, transferts_terrain, terrain_coproprietaires, tnb_documents
 * Boissons : etablissements_boissons, declarations_annuelles_tdb
 * Transport: vehicules
 * ODP      : occupations
 * Location : loc_secteurs, loc_tarifs, loc_boutiques, baux
 * Souk     : affermages, affermage_docs
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── TNB ────────────────────────────────────────────────
        Schema::create('tnb_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique()->comment('dossiers_tnb.numero_dossier');
            $table->foreignId('taxpayer_id')->unique()->constrained()->restrictOnDelete();
            $table->boolean('is_archived')->default(false);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('parcels', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique()->comment('terrains.numero_terrain');
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('address_ar')->nullable();
            $table->string('district')->nullable()->comment('quartier');
            $table->string('borough')->nullable()->comment('arrondissement');
            $table->string('subdivision')->nullable()->index()->comment('lotissement');
            $table->decimal('area', 14, 2)->nullable()->comment('superficie m²');
            $table->string('zone', 10)->default('B')->index();
            $table->string('land_title', 60)->nullable()->index()->comment('titre foncier');
            $table->string('parcel_number', 60)->nullable()->index()->comment('n° parcelle / lot');
            $table->string('status', 30)->default('non_bati');
            $table->date('acquired_on')->nullable();
            $table->unsignedSmallInteger('tax_start_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_census')->default(false)->comment('terrain recensé sans dossier officiel');
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('building_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->nullable()->comment('construction…');
            $table->string('number', 60)->nullable();
            $table->date('filed_on')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('authorized_on')->nullable();
            $table->string('status', 30)->default('en_cours');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('exemption_start_year')->nullable();
            $table->unsignedSmallInteger('exemption_end_year')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('parcel_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_taxpayer_id')->nullable()->constrained('taxpayers')->nullOnDelete();
            $table->foreignId('to_taxpayer_id')->nullable()->constrained('taxpayers')->nullOnDelete();
            $table->date('transferred_on')->nullable();
            $table->string('reason')->nullable();
            $table->string('notarial_deed')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('parcel_co_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('taxpayer_id')->constrained()->cascadeOnDelete();
            $table->decimal('share_percent', 7, 4)->default(0)->comment('part d’indivision (%)');
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['parcel_id', 'taxpayer_id']);
        });

        Schema::create('parcel_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->string('file_name')->nullable();
            $table->string('path')->nullable();
            $table->string('type', 40)->default('autre');
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // ── Débits de boissons ─────────────────────────────────
        Schema::create('beverage_outlets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('type', 60)->nullable()->comment('café, restaurant, bar, hôtel…');
            $table->string('address')->nullable();
            $table->decimal('area', 14, 2)->nullable();
            $table->string('authorization_number', 60)->nullable();
            $table->date('authorized_on')->nullable();
            $table->unsignedTinyInteger('tax_start_quarter')->default(1);
            $table->unsignedSmallInteger('tax_start_year')->nullable();
            $table->string('status', 20)->default('actif');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('beverage_annual_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->foreignId('beverage_outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year')->index();
            $table->decimal('base_q1', 14, 2)->default(0);
            $table->decimal('base_q2', 14, 2)->default(0);
            $table->decimal('base_q3', 14, 2)->default(0);
            $table->decimal('base_q4', 14, 2)->default(0);
            $table->decimal('total_base', 14, 2)->default(0);
            $table->decimal('rate', 8, 4)->default(10);
            $table->decimal('amount_due', 14, 2)->default(0);
            $table->date('declared_on')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('soumise');
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // ── Transport (stationnement + TPV) ────────────────────
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('registration', 40)->nullable()->index()->comment('immatriculation');
            $table->string('type', 60)->nullable();
            $table->string('authorization_number', 60)->nullable()->comment('agrément');
            $table->date('authorized_on')->nullable();
            $table->unsignedSmallInteger('seats')->default(0);
            $table->unsignedTinyInteger('tax_start_quarter')->default(1);
            $table->unsignedSmallInteger('tax_start_year')->nullable();
            $table->string('status', 20)->default('actif');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // ── Domaine public ─────────────────────────────────────
        Schema::create('public_occupations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 60)->nullable();
            $table->string('location')->nullable();
            $table->decimal('area', 14, 2)->nullable();
            $table->string('authorization_number', 60)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedTinyInteger('tax_start_quarter')->default(1);
            $table->unsignedSmallInteger('tax_start_year')->nullable();
            $table->string('status', 20)->default('actif');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // ── Locations ──────────────────────────────────────────
        Schema::create('lease_sectors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('tariff_type', 10)->default('fixe')->comment('fixe = DH/mois, m2 = DH/m²/mois');
            $table->decimal('monthly_rate', 14, 2)->default(0);
            $table->string('unit', 20)->default('DH/mois');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('lease_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_sector_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('tariff_type', 10)->default('fixe');
            $table->decimal('value', 14, 2)->default(0);
            $table->string('unit', 20)->default('DH/mois');
            $table->boolean('is_active')->default(true);
            $table->foreignId('tariff_id')->nullable()->constrained()->nullOnDelete()->comment('tarif de l’arrêté fiscal synchronisé');
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_tariff_id')->constrained()->restrictOnDelete();
            $table->string('number', 40);
            $table->string('label')->nullable();
            $table->decimal('area', 14, 2)->default(0);
            $table->string('status', 20)->default('disponible');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique()->comment('baux.numero');
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('premises_ref')->nullable()->comment('ref_local');
            $table->string('address')->nullable();
            $table->decimal('area', 14, 2)->nullable();
            $table->decimal('monthly_rent', 14, 2)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('actif');
            $table->boolean('is_active')->default(true);
            $table->foreignId('lease_sector_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lease_tariff_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // ── Souk (affermage) ───────────────────────────────────
        Schema::create('souk_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('souk_name')->nullable();
            $table->string('pitch_number', 40)->nullable()->comment('num_emplacement');
            $table->string('activity')->nullable();
            $table->decimal('annual_fee', 14, 2)->nullable();
            $table->decimal('monthly_fee', 14, 2)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedTinyInteger('duration_years')->default(1);
            $table->decimal('increase_rate', 8, 4)->default(5)->comment('% de revalorisation à chaque renouvellement');
            $table->string('status', 20)->default('actif');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('souk_contract_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('souk_contract_id')->constrained()->cascadeOnDelete();
            $table->string('file_name')->nullable();
            $table->string('path')->nullable();
            $table->string('type', 40)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'souk_contract_documents', 'souk_contracts', 'leases', 'shops', 'lease_tariffs', 'lease_sectors',
            'public_occupations', 'vehicles', 'beverage_annual_returns', 'beverage_outlets',
            'parcel_documents', 'parcel_co_owners', 'parcel_transfers', 'building_permits', 'parcels', 'tnb_files',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
