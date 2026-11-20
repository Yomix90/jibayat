<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Régie des recettes (demande youssef du 20/11/2026) : saisie des recettes (brouillon ➜ validation),
 * livre de caisse, clôture mensuelle, bordereaux de versement, envois au Trésor, procès-verbaux,
 * registre des documents imprimés (QR), boîtes d'archive, stock tickets / vignettes et décharges.
 * Montants en DECIMAL(14,2) ; pièces officielles annulées avec motif, jamais supprimées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rubrics', function (Blueprint $table) {
            $table->string('family', 40)->nullable()->after('module')->comment('famille du récap comptable');
            $table->boolean('manual_entry')->default(true)->after('family')->comment('saisie directe à la régie');
            $table->boolean('special_tax')->default(false)->after('manual_entry')->comment('taxe spéciale (abattage)');
        });

        // Rubriques des modules : encaissées par bulletin (pas de saisie directe), familles du récap comptable.
        $families = ['TNB' => 'fiscalite', 'DEBITS_BOISSONS' => 'fiscalite', 'TRANSPORT_VOYAGEURS' => 'fiscalite', 'STATIONNEMENT' => 'fiscalite',
            'OCCUPATION_DOMAINE' => 'domaine', 'LOCATION_LOCAUX' => 'domaine', 'AFFERMAGE_SOUKS' => 'domaine', 'FOURRIERE' => 'services'];
        foreach ($families as $module => $family) {
            DB::table('rubrics')->where('module', $module)->update(['family' => $family, 'manual_entry' => false]);
        }

        Schema::table('stamp_values', function (Blueprint $table) {
            $table->foreignId('rubric_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->unsignedInteger('alert_threshold')->default(0)->after('units_per_book')->comment('stock régie minimal (unités)');
        });

        Schema::create('cash_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->date('entry_date')->index();
            $table->foreignId('rubric_id')->constrained();
            $table->string('payment_mode', 30)->default('especes');
            $table->decimal('quantity', 14, 2)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('reference', 60)->nullable()->index()->comment('n° de quittance, ticket, chèque…');
            $table->string('payer')->nullable();
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('brouillon')->index()->comment('brouillon | valide');
            $table->string('source', 30)->nullable()->comment('saisie | decharge (tickets soldés)');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['entry_date', 'status']);
        });

        Schema::create('cash_closures', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('receipts', 14, 2)->default(0);
            $table->decimal('remittances', 14, 2)->default(0);
            $table->decimal('closing_balance', 14, 2)->default(0);
            $table->json('snapshot')->nullable()->comment('état mensuel (Modèle 10) figé');
            $table->timestamp('closed_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reopen_reason')->nullable();
            $table->timestamps();
            $table->index(['year', 'month']);
        });

        Schema::create('cash_remittances', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->date('remitted_on')->index();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('payment_mode', 30)->default('especes')->comment('espèces, chèques, virement');
            $table->decimal('amount', 14, 2);
            $table->json('lines')->nullable()->comment('détail par rubrique');
            $table->string('accountant')->nullable()->comment('comptable de rattachement');
            $table->string('bank_reference', 60)->nullable();
            $table->string('status', 20)->default('verse')->comment('verse | accuse');
            $table->date('acknowledged_on')->nullable();
            $table->string('acknowledgement_ref', 60)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('treasury_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->date('sent_on')->index();
            $table->string('recipient')->nullable();
            $table->json('items')->comment('pièces envoyées : type, référence, libellé, nombre');
            $table->date('acknowledged_on')->nullable();
            $table->string('acknowledged_by_name')->nullable()->comment('receveur ayant accusé réception');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_reports', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('type', 20)->index()->comment('arrete | verification | recolement | remise');
            $table->date('report_date')->index();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('period')->default(0)->comment('mois (arrêté) ou trimestre');
            $table->decimal('theoretical_balance', 14, 2)->default(0);
            $table->decimal('counted_balance', 14, 2)->default(0);
            $table->decimal('difference', 14, 2)->default(0);
            $table->json('data')->nullable()->comment('billetage, stock compté, intervenants…');
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('archive_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        Schema::create('printed_documents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('token', 16)->comment('code de contrôle imprimé avec le QR');
            $table->string('fingerprint', 64)->unique()->comment('vue + nom de fichier');
            $table->string('type', 80)->index();
            $table->string('title');
            $table->string('url', 500)->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_printed_at');
            $table->timestamp('last_printed_at');
            $table->unsignedInteger('print_count')->default(1);
            $table->string('scan_path')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archive_box_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stamp_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stamp_value_id')->constrained();
            $table->string('movement', 20)->comment('dotation (réserve TGR) | retrait (TGR ➜ régie) | ajustement_tgr | ajustement_regie');
            $table->integer('quantity')->comment('unités (négatif pour un ajustement à la baisse)');
            $table->date('moved_on')->index();
            $table->unsignedSmallInteger('year')->index();
            $table->string('reference', 60)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_discharges', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->date('discharge_date')->index();
            $table->foreignId('stamp_value_id')->constrained();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('place')->nullable()->comment('souk, marché…');
            $table->string('first_number', 30)->nullable();
            $table->string('last_number', 30)->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('returned')->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('status', 20)->default('remis')->comment('remis | solde');
            $table->date('settled_on')->nullable();
            $table->foreignId('cash_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->json('corrections')->nullable()->comment('historique des modifications (motif, auteur, date)');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_discharges');
        Schema::dropIfExists('stamp_stock_movements');
        Schema::dropIfExists('printed_documents');
        Schema::dropIfExists('archive_boxes');
        Schema::dropIfExists('cash_reports');
        Schema::dropIfExists('treasury_dispatches');
        Schema::dropIfExists('cash_remittances');
        Schema::dropIfExists('cash_closures');
        Schema::dropIfExists('cash_entries');
        Schema::table('stamp_values', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rubric_id');
            $table->dropColumn('alert_threshold');
        });
        Schema::table('rubrics', function (Blueprint $table) {
            $table->dropColumn(['family', 'manual_entry', 'special_tax']);
        });
    }
};
