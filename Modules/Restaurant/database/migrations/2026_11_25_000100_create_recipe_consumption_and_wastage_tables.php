<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recipes and sub-recipes, the stock a sale consumed, and wastage entries (Step 5.7). Inventory items are referenced by
 * id only (Inventory is another module).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_order_lines', function (Blueprint $table): void {
            $table->json('modifier_ids')->nullable()->after('modifiers');
        });

        Schema::create('recipes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);
            $table->foreignId('menu_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100)->nullable();
            $table->decimal('yield_quantity', 15, 4)->default(1);
            $table->string('yield_unit', 20)->nullable();
            $table->string('notes', 300)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'kind']);
            $table->index(['tenant_id', 'menu_item_id']);
        });

        Schema::create('recipe_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->foreignId('sub_recipe_id')->nullable()->constrained('recipes')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('waste_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'recipe_id']);
        });

        Schema::create('sale_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_bill_line_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('store_id');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->boolean('is_complimentary')->default(false);
            $table->date('business_date');
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->unsignedBigInteger('reversal_movement_id')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->unique(['pos_bill_line_id', 'item_id']);
            $table->index(['tenant_id', 'pos_bill_id']);
            $table->index(['tenant_id', 'property_id', 'business_date']);
        });

        Schema::create('wastage_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('wastage_no', 30);
            $table->unsignedBigInteger('store_id');
            $table->date('wasted_on');
            $table->string('reason', 15);
            $table->string('notes', 300)->nullable();
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'wastage_no']);
            $table->index(['tenant_id', 'property_id', 'wasted_on']);
            $table->index(['tenant_id', 'source_type', 'source_id']);
        });

        Schema::create('wastage_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('wastage_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'wastage_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_order_lines', function (Blueprint $table): void {
            $table->dropColumn('modifier_ids');
        });

        foreach (['wastage_lines', 'wastage_entries', 'sale_consumptions', 'recipe_lines', 'recipes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
