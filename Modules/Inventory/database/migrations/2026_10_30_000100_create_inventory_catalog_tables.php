<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The inventory catalogue (Step 5.2): units and their conversions, item categories, items, per-item conversions,
 * stores of a property and per-store par and reorder levels. Quantities have four decimals. Stock itself
 * (movements, balances) comes in Step 5.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 12);
            $table->string('name', 60);
            $table->string('kind', 10);
            $table->foreignId('base_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('factor', 18, 6)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('item_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'parent_id']);
        });

        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_category_id')->constrained()->restrictOnDelete();
            $table->string('sku', 40);
            $table->string('name', 150);
            $table->string('barcode', 50)->nullable();
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('purchase_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('issue_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->boolean('is_stockable')->default(true);
            $table->boolean('track_batches')->default(false);
            $table->boolean('track_expiry')->default(false);
            $table->decimal('reorder_level', 15, 4)->default(0);
            $table->decimal('reorder_qty', 15, 4)->default(0);
            $table->decimal('standard_cost', 15, 4)->nullable();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->unique(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'item_category_id']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('item_unit_conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('factor', 18, 6);
            $table->timestamps();

            $table->unique(['item_id', 'unit_id']);
        });

        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('kind', 15);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'property_id', 'code']);
        });

        Schema::create('item_store_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->decimal('par_level', 15, 4)->nullable();
            $table->decimal('reorder_level', 15, 4)->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_store_levels');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('item_unit_conversions');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
        Schema::dropIfExists('units');
    }
};
