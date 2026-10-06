<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room service, location delivery and staff meals (ARCHITECTURE §5.10.4) and restaurant table
 * reservations (§5.10.12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('reservation_id')->nullable()->after('waiter_id');
            $table->string('guest_name', 190)->nullable()->after('reservation_id');
            $table->string('delivery_location', 190)->nullable()->after('guest_name');
            $table->string('delivery_status', 20)->nullable()->after('delivery_location');
            $table->timestamp('out_for_delivery_at')->nullable()->after('delivery_status');
            $table->timestamp('delivered_at')->nullable()->after('out_for_delivery_at');
            $table->index(['tenant_id', 'outlet_id', 'delivery_status']);
        });

        Schema::create('table_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->string('customer_name', 190);
            $table->string('phone', 40)->nullable();
            $table->timestamp('reserved_for');
            $table->unsignedSmallInteger('duration_minutes')->default(90);
            $table->unsignedSmallInteger('party_size');
            $table->string('occasion', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('status', 20)->default('booked');
            $table->foreignId('pos_order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'outlet_id', 'reserved_for']);
            $table->index(['tenant_id', 'dining_table_id', 'reserved_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_reservations');

        Schema::table('pos_orders', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'outlet_id', 'delivery_status']);
            $table->dropColumn(['reservation_id', 'guest_name', 'delivery_location', 'delivery_status', 'out_for_delivery_at', 'delivered_at']);
        });
    }
};
