<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chart of accounts, fiscal years and periods, and journal entries with their lines and dimensions
 * (ARCHITECTURE §5.14, §7.2). Amounts are in the tenant's base currency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name', 190);
            $table->string('type', 20);
            $table->boolean('is_group')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('system_key', 60)->nullable();
            $table->string('usali_department', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('description', 300)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'system_key']);
            $table->index(['tenant_id', 'parent_id']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('fiscal_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'starts_on', 'ends_on']);
        });

        Schema::create('fiscal_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedTinyInteger('number');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 10)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'fiscal_year_id', 'number']);
            $table->index(['tenant_id', 'starts_on', 'ends_on']);
        });

        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('entry_no', 30)->nullable();
            $table->date('entry_date');
            $table->foreignId('fiscal_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 10)->default('draft');
            $table->string('description', 300);
            $table->string('reference', 100)->nullable();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_event', 60)->nullable();
            $table->foreignId('reverses_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->decimal('total', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'entry_no']);
            $table->unique(['tenant_id', 'source_type', 'source_id', 'source_event'], 'journal_entries_source_unique');
            $table->unique(['tenant_id', 'reverses_id']);
            $table->index(['tenant_id', 'status', 'entry_date']);
            $table->index(['tenant_id', 'fiscal_period_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('description', 300)->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('party_type', 30)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'journal_entry_id']);
            $table->index(['tenant_id', 'account_id']);
            $table->index(['tenant_id', 'property_id', 'department_id']);
            $table->index(['tenant_id', 'party_type', 'party_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('accounts');
    }
};
