<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary setup (Step 6.4, ARCHITECTURE §5.16).
 *
 * - salary_components name an earning, a deduction or an employer cost; salary_structures say how each is worked out
 *   (a fixed amount, a percentage of another component, a formula, or an amount set per employee).
 * - employee_salaries are effective-dated: the monthly basic, the structure and the tax settings from a date.
 * - tax_tables and tax_slabs hold income tax rules as data, so no country's rules are in the code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->boolean('is_taxable')->default(false);
            $table->string('frequency', 10)->default('monthly');
            $table->unsignedTinyInteger('annual_times')->default(1);
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'kind']);
        });

        Schema::create('salary_structures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('description', 300)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'code']);
        });

        Schema::create('salary_structure_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->cascadeOnDelete();
            $table->string('calc_type', 10);
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percent', 7, 2)->nullable();
            $table->string('percent_of', 20)->nullable();
            $table->string('formula', 300)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['salary_structure_id', 'salary_component_id'], 'salary_structure_components_unique');
            $table->index(['tenant_id', 'salary_structure_id'], 'salary_structure_components_tenant_index');
        });

        Schema::create('employee_salaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('basic', 15, 2);
            $table->json('amounts')->nullable();
            $table->string('tax_category', 30)->default('general');
            $table->string('tax_area', 30)->default('other');
            $table->string('notes', 300)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'effective_from']);
            $table->index(['tenant_id', 'employee_id', 'effective_from'], 'employee_salaries_lookup_index');
        });

        Schema::create('tax_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('tax_year', 20);
            $table->date('effective_from');
            $table->json('thresholds');
            $table->json('minimum_tax');
            $table->string('exemption_fraction', 20)->default('0');
            $table->decimal('exemption_cap', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'effective_from']);
        });

        Schema::create('tax_slabs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_table_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->decimal('width', 15, 2)->nullable();
            $table->decimal('rate', 5, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'tax_table_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_slabs');
        Schema::dropIfExists('tax_tables');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('salary_structure_components');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('salary_components');
    }
};
