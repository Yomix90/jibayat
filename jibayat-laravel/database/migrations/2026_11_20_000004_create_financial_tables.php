<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pièces financières : déclarations (liquidations), périodes, bulletins de versement / quittances,
 * avis de non-paiement, lettres de relance, journal des imports.
 * Flask : declarations, bulletins, avis_non_paiement, lettres_notification, lettres_details, imports_historique.
 *
 * Pièces immuables (CLAUDE.md §2.5) : pas de suppression, annulation motivée
 * (cancelled_at, cancelled_by, cancellation_reason) visible dans « Pièces annulées ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_declarations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique()->comment('declarations.numero');
            $table->string('module', 40)->comment('TNB, DEBITS_BOISSONS, STATIONNEMENT, OCCUPATION_DOMAINE, FOURRIERE, LOCATION_LOCAUX, AFFERMAGE_SOUKS');
            $table->nullableMorphs('taxable');  // terrain, établissement, véhicule, occupation, dossier fourrière, bail, contrat souk
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('quarter')->default(0)->comment('0 = année / sans trimestre');
            $table->decimal('base_amount', 14, 2)->default(0)->comment('base_calcul (superficie, CA, tarif TPV…)');
            $table->decimal('rate', 14, 4)->default(0)->comment('taux ou tarif unitaire (STA : tarif stationnement)');
            $table->decimal('principal', 14, 2)->default(0);
            $table->decimal('late_penalty', 14, 2)->default(0)->comment('penalite_retard');
            $table->decimal('surcharge', 14, 2)->default(0)->comment('majoration');
            $table->decimal('non_filing_fine', 14, 2)->default(0)->comment('amende_non_declaration');
            $table->decimal('total', 14, 2)->default(0);
            $table->string('status', 20)->default('emis')->comment('emis | sous_seuil | paye | annule');
            $table->date('declared_on')->nullable();
            $table->date('due_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['module', 'year', 'status']);
            $table->index(['taxpayer_id', 'status']);
            $table->index(['module', 'taxable_id', 'year', 'quarter']);
        });

        // Mois réglés par une déclaration Location / Souk (Flask : liste « 2025-01, 2025-02 » dans notes).
        Schema::create('tax_declaration_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_declaration_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7)->comment('AAAA-MM');
            $table->decimal('amount', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['tax_declaration_id', 'period']);
            $table->index('period');
        });

        Schema::create('payment_slips', function (Blueprint $table) {
            $table->id();
            $table->string('slip_number', 40)->nullable()->index()->comment('N° BV (peut être partagé par plusieurs déclarations)');
            $table->foreignId('tax_declaration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('payment_mode', 30)->default('especes');
            $table->date('paid_on')->nullable()->index()->comment('date_paiement');
            $table->string('status', 20)->default('en_attente')->index()->comment('en_attente | paye | rejete | encaisse | valide | annule');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete()->comment('régisseur ayant validé / rejeté');
            $table->string('receipt_number', 40)->nullable()->index()->comment('N° de quittance');
            $table->date('receipt_date')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('transfer_number', 40)->nullable()->comment('numero_versement');
            $table->date('collected_on')->nullable()->comment('date_encaissement');
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['tax_declaration_id', 'status']);
        });

        Schema::create('reminder_letters', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->string('batch_id', 40)->nullable()->index();
            $table->string('module', 40)->nullable();
            $table->string('type', 30)->default('relance');
            $table->string('status', 20)->default('brouillon');
            $table->date('generated_on')->nullable();
            $table->date('sent_on')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('taxpayer_count')->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('non_payment_notices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('tax_declaration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_due', 14, 2)->default(0);
            $table->date('issued_on')->nullable();
            $table->unsignedSmallInteger('delay_days')->default(30);
            $table->string('batch_id', 40)->nullable()->index();
            $table->string('status', 20)->default('emis');
            $table->foreignId('reminder_letter_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('reminder_letter_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('non_payment_notice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tax_declaration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_due', 14, 2)->default(0);
            $table->string('delivery_status', 20)->default('inclus');
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 40);
            $table->string('file_name')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('details')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['import_logs', 'reminder_letter_items', 'non_payment_notices', 'reminder_letters', 'payment_slips', 'tax_declaration_periods', 'tax_declarations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
