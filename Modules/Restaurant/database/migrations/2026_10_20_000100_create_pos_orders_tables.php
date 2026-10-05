<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS orders and kitchen tickets (ARCHITECTURE §5.10.4–5.10.6, §8.4): an order at a table or for
 * takeaway, its lines (price and modifiers as snapshots, course, seat, station, hold, void) and the
 * KOTs a Send makes, one per station, numbered per outlet and business date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_no', 20);
            $table->date('business_date');
            $table->string('order_type', 20);
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('covers')->default(1);
            $table->foreignId('waiter_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('open');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('merged_into_id')->nullable()->constrained('pos_orders')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'business_date', 'order_no']);
            $table->index(['tenant_id', 'outlet_id', 'status']);
            $table->index(['tenant_id', 'dining_table_id', 'status']);
        });

        Schema::create('pos_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_item_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_snapshot', 190);
            $table->string('variant_snapshot', 60)->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->json('modifiers')->nullable();
            $table->decimal('modifier_total', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->string('course', 20);
            $table->unsignedTinyInteger('seat_no')->nullable();
            $table->foreignId('kitchen_station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_held')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->string('notes', 300)->nullable();
            $table->string('void_reason', 30)->nullable();
            $table->string('void_note', 300)->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('manager_approval_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_wastage')->default(false);
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'pos_order_id', 'status']);
        });

        Schema::create('kots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_station_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('kot_no');
            $table->date('business_date');
            $table->string('type', 10)->default('new');
            $table->string('status', 20)->default('new');
            $table->timestamp('fired_at');
            $table->timestamp('printed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'business_date', 'kot_no']);
            $table->index(['tenant_id', 'kitchen_station_id', 'status']);
        });

        Schema::create('kot_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('kot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_order_line_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->string('status', 20)->default('new');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kot_lines');
        Schema::dropIfExists('kots');
        Schema::dropIfExists('pos_order_lines');
        Schema::dropIfExists('pos_orders');
    }
};
