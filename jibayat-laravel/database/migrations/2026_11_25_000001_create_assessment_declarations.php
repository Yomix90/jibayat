<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Déclarations relatives à l'assiette (loi 47-06 modifiée par la loi 07-20) : TNB (art. 47, 48),
 * débits de boissons (art. 67, 68, 69), transport public de voyageurs (art. 87), plus la reprise d'activité.
 * Prolongations des contrats de souk avec augmentation, et suivi des contacts pour le renouvellement des autorisations ODP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_declarations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('module', 40)->index();
            $table->string('type', 40)->index();
            $table->string('declarable_type', 40)->nullable();
            $table->unsignedBigInteger('declarable_id')->nullable();
            $table->foreignId('taxpayer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year')->nullable()->comment('année concernée (déclarations annuelles)');
            $table->date('event_on')->nullable()->comment('date du fait générateur (cessation, cession…)');
            $table->date('filed_on')->comment('date de dépôt');
            $table->date('deadline')->nullable();
            $table->boolean('is_late')->default(false);
            $table->date('resume_on')->nullable()->comment('reprise prévue (cessation) ou effective (reprise)');
            $table->string('reason')->nullable();
            $table->decimal('amount', 14, 2)->nullable()->comment('chiffre d’affaires déclaré, superficie…');
            $table->json('details')->nullable();
            $table->boolean('signed')->default(false)->comment('déclaration signée par le redevable');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['declarable_type', 'declarable_id']);
        });

        Schema::create('souk_contract_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('souk_contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('renewal_no')->comment('n° de période (0 = période initiale)');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('monthly_fee', 14, 2);
            $table->decimal('increase_rate', 6, 2)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['souk_contract_id', 'renewal_no']);
        });

        Schema::table('public_occupations', function (Blueprint $table) {
            $table->timestamp('renewal_contacted_at')->nullable()->after('status');
            $table->string('renewal_contact_note')->nullable()->after('renewal_contacted_at');
        });
    }

    public function down(): void
    {
        Schema::table('public_occupations', fn (Blueprint $t) => $t->dropColumn(['renewal_contacted_at', 'renewal_contact_note']));
        Schema::dropIfExists('souk_contract_renewals');
        Schema::dropIfExists('assessment_declarations');
    }
};
