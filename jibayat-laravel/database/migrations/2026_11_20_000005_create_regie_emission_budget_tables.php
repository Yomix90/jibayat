<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Régie des timbres (regie_*), relevés GIR / bordereaux d'émission (bordereaux_versement,
 * lignes_recettes, bordereaux_emission, emission_config) et budget (regie_budget).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamp_values', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->default('timbre');
            $table->string('label');
            $table->decimal('unit_value', 14, 2);
            $table->unsignedInteger('units_per_book');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number', 40)->nullable()->comment('matricule');
            $table->string('last_name');
            $table->string('first_name')->nullable();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('stamp_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stamp_value_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->string('first_number', 30)->nullable();
            $table->string('last_number', 30)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->date('received_on')->nullable();
            $table->string('status', 20)->default('recu');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('stamp_remittances', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique()->comment('bordereau de versement au percepteur');
            $table->date('remitted_on')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('stamp_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stamp_pack_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stamp_value_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->unsignedBigInteger('first_number')->nullable();
            $table->unsignedBigInteger('last_number')->nullable();
            $table->string('status', 20)->default('en_stock')->index()->comment('en_stock | affecte | consomme');
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->date('assigned_on')->nullable();
            $table->date('employee_paid_on')->nullable();
            $table->date('remitted_on')->nullable();
            $table->foreignId('stamp_remittance_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('difference', 14, 2)->default(0)->comment('écart = versé − valeur théorique');
            $table->text('observation')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('treasury_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('source_file')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->decimal('grand_total', 14, 2)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['year', 'month']);
        });

        Schema::create('treasury_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treasury_statement_id')->constrained()->cascadeOnDelete();
            $table->string('budget_code', 20)->nullable()->index();
            $table->string('nature')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('issue_sheet')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('issue_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treasury_statement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number')->nullable()->comment('n° séquentiel dans l’année');
            $table->string('rubric')->nullable();
            $table->string('budget_code', 20)->nullable()->index();
            $table->string('title')->nullable();
            $table->decimal('current_amount', 14, 2)->nullable();
            $table->decimal('carried_forward', 14, 2)->nullable()->comment('report antérieur');
            $table->decimal('total', 14, 2)->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('xlsx_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('issue_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('first_part_mode', 10)->default('auto');
            $table->decimal('first_part_value', 14, 2)->default(0);
            $table->string('second_part_mode', 10)->default('vide');
            $table->decimal('second_part_value', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->index();
            $table->string('chapter', 30);
            $table->string('gir_code', 20)->nullable();
            $table->string('label');
            $table->string('label_fr')->nullable();
            $table->string('label_ar')->nullable();
            $table->string('domain', 30);
            $table->string('domain_fr')->nullable();
            $table->string('domain_ar')->nullable();
            $table->string('module', 40)->nullable()->comment('module Jibayat associé (synchronisation du réalisé)');
            $table->decimal('previous_actual', 14, 2)->default(0)->comment('montant_realise_n1');
            $table->decimal('proposed', 14, 2)->default(0);
            $table->decimal('actual', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['year', 'chapter']);
        });
    }

    public function down(): void
    {
        foreach ([
            'budget_lines', 'issue_configs', 'issue_slips', 'treasury_statement_lines', 'treasury_statements',
            'stamp_books', 'stamp_remittances', 'stamp_packs', 'employees', 'services', 'stamp_values',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
