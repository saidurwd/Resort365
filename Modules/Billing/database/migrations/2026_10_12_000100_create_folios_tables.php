<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Folios (ARCHITECTURE §5.9, §8.3): charge codes (tenant-wide), the extras catalogue (per
 * property), folios per reservation with their lines, and routing rules ("company pays room").
 * Amounts are DECIMAL(15,2); posting_date is the property's business date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('category', 20);
            $table->foreignId('tax_category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('extra_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('charge_code_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->boolean('price_includes_tax')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'property_id']);
        });

        Schema::create('folios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('folio_no', 30);
            $table->string('type', 20)->default('guest');
            $table->string('bill_to_type', 20)->default('guest');
            $table->unsignedBigInteger('bill_to_id')->nullable();
            $table->string('name', 150);
            $table->string('status', 20)->default('open');
            $table->char('currency_code', 3);
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'folio_no']);
            $table->index(['tenant_id', 'reservation_id']);
        });

        Schema::create('folio_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $table->date('posting_date');
            $table->string('line_type', 20);
            $table->foreignId('charge_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('extra_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('revenue_posted_by_source')->default(false);
            $table->foreignId('routed_from_folio_id')->nullable()->constrained('folios')->nullOnDelete();
            $table->boolean('is_voided')->default(false);
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'folio_id']);
            $table->index(['tenant_id', 'property_id', 'posting_date']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('folio_routing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->foreignId('target_folio_id')->constrained('folios')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['reservation_id', 'category']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreign('folio_id')->references('id')->on('folios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['folio_id']);
        });
        Schema::dropIfExists('folio_routing_rules');
        Schema::dropIfExists('folio_lines');
        Schema::dropIfExists('folios');
        Schema::dropIfExists('extra_services');
        Schema::dropIfExists('charge_codes');
    }
};
