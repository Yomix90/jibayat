<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel : commune, modules, contribuables, rubriques, arrêtés fiscaux, tarifs, paramètres de calcul.
 * Flask : communes, app_modules, contribuables, ctb_documents, rubriques, arretes_fiscaux, tarifs, parametres_calcul.
 * Chaque table garde « legacy_id » (id Flask) pour un import rejouable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('president')->nullable();
            $table->string('president_ar')->nullable();
            $table->string('region')->nullable();
            $table->string('region_ar')->nullable();
            $table->string('province')->nullable();
            $table->string('province_ar')->nullable();
            $table->string('logo')->nullable();
            $table->string('code', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('app_modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label')->nullable();
            $table->string('description')->nullable();
            $table->string('icon', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(10);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('taxpayers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique()->comment('contribuables.numero');
            $table->string('person_type', 20)->default('physique');
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name_ar')->nullable();
            $table->string('first_name_ar')->nullable();
            $table->string('company_name')->nullable();
            $table->string('company_name_ar')->nullable();
            $table->string('cin', 30)->nullable()->index();
            $table->string('ice', 30)->nullable()->index();
            $table->string('rc', 30)->nullable()->index();
            $table->string('address')->nullable();
            $table->string('address_ar')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['is_active', 'last_name']);
        });

        Schema::create('taxpayer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxpayer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->default('autre');
            $table->string('file_name')->nullable();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('rubrics', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable()->index()->comment('code budgétaire');
            $table->string('label');
            $table->string('label_ar')->nullable();
            $table->string('module', 40)->unique()->comment('code module Flask (TNB, DEBITS_BOISSONS…)');
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('tax_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique()->comment('arretes_fiscaux.numero');
            $table->string('title')->nullable();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('actif');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 60)->nullable()->index();
            $table->string('label');
            $table->decimal('value', 14, 4)->comment('montant, taux ou tarif unitaire selon unit');
            $table->string('unit', 30)->default('DH');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('surface_min', 14, 2)->default(0);
            $table->decimal('surface_max', 14, 2)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['rubric_id', 'is_active', 'start_date']);
        });

        Schema::create('calc_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('module', 40);
            $table->string('code', 60);
            $table->string('label')->nullable();
            $table->string('value')->nullable()->comment('texte : « 31/03 », « 15 »…');
            $table->string('unit', 20)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['module', 'code']);
        });
    }

    public function down(): void
    {
        foreach (['calc_parameters', 'tariffs', 'tax_orders', 'rubrics', 'taxpayer_documents', 'taxpayers', 'app_modules', 'municipalities'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
