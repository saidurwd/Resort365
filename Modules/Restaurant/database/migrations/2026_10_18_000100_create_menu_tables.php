<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The menu (ARCHITECTURE §5.10.2, §8.4): nested categories, items with multi-language names
 * (json, one key per menu language), variants, modifier groups and modifiers, combo components,
 * per-outlet schedules, and outlet_menu_items: what each outlet sells, at what price, prepared at
 * which of its stations, and whether it is sold out (86). variant_key is the variant id or 0, so
 * the price row is unique also for items without variants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_categories')->restrictOnDelete();
            $table->json('name');
            $table->string('colour', 20)->default('primary');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'parent_id']);
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_category_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('course', 20);
            $table->foreignId('tax_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('dish');
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->json('dietary_tags')->nullable();
            $table->json('allergens')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'property_id', 'code']);
            $table->index(['tenant_id', 'menu_category_id']);
        });

        Schema::create('menu_item_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'menu_item_id', 'name']);
        });

        Schema::create('modifier_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'property_id', 'name']);
        });

        Schema::create('modifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('price_delta', 15, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_item_modifier_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'menu_item_id', 'modifier_group_id'], 'menu_item_modifier_groups_unique');
        });

        Schema::create('combo_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->foreignId('component_variant_id')->nullable()->constrained('menu_item_variants')->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'menu_item_id']);
        });

        Schema::create('menu_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('days_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('price_adjustment_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'name']);
        });

        Schema::create('outlet_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('variant_key')->default(0);
            $table->decimal('price', 15, 2);
            $table->foreignId('kitchen_station_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_package_eligible')->default(false);
            $table->json('schedule_ids')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'menu_item_id', 'variant_key'], 'outlet_menu_items_unique');
            $table->index(['tenant_id', 'menu_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_menu_items');
        Schema::dropIfExists('menu_schedules');
        Schema::dropIfExists('combo_components');
        Schema::dropIfExists('menu_item_modifier_groups');
        Schema::dropIfExists('modifiers');
        Schema::dropIfExists('modifier_groups');
        Schema::dropIfExists('menu_item_variants');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
    }
};
