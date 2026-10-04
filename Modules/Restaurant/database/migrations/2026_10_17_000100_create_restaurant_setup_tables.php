<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant setup (ARCHITECTURE §5.10.1, §5.10.3, §8.4): outlets, printers, kitchen stations, POS
 * terminals, which staff work in which outlet, and the floor plan (dining areas and tables).
 * outlets.store_id (Inventory) and the bill numbering arrive with their steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->string('type', 20);
            $table->boolean('prices_include_tax')->default(false);
            $table->foreignId('default_tax_category_id')->nullable()->constrained('tax_categories')->nullOnDelete();
            $table->string('bill_prefix', 10);
            $table->string('receipt_header', 500)->nullable();
            $table->string('receipt_footer', 500)->nullable();
            $table->json('opening_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'property_id', 'code']);
        });

        Schema::create('printers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('type', 20);
            $table->string('connection', 20);
            $table->string('address', 190)->nullable();
            $table->unsignedSmallInteger('paper_width_mm')->default(80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'property_id']);
        });

        Schema::create('kitchen_stations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('output', 20);
            $table->foreignId('printer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'name']);
        });

        Schema::create('pos_terminals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('device_token', 64);
            $table->foreignId('receipt_printer_id')->nullable()->constrained('printers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique('device_token');
            $table->index(['tenant_id', 'outlet_id']);
        });

        Schema::create('outlet_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('dining_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'name']);
        });

        Schema::create('dining_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_area_id')->constrained()->cascadeOnDelete();
            $table->string('number', 10);
            $table->unsignedSmallInteger('seats');
            $table->string('shape', 20);
            $table->unsignedSmallInteger('pos_x')->default(0);
            $table->unsignedSmallInteger('pos_y')->default(0);
            $table->string('status', 20)->default('available');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'number']);
            $table->index(['tenant_id', 'dining_area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('dining_areas');
        Schema::dropIfExists('outlet_user');
        Schema::dropIfExists('pos_terminals');
        Schema::dropIfExists('kitchen_stations');
        Schema::dropIfExists('printers');
        Schema::dropIfExists('outlets');
    }
};
