<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservations (ARCHITECTURE §8.3). A reservation has items (a room, or a whole cottage), a
 * nightly price snapshot per item (later rate changes never alter it) and its guests. Amounts are
 * DECIMAL(15,2) in the property's currency; stay dates are DATE. Reservations are cancelled,
 * never deleted. rate_plan_id is the plan whose deposit and cancellation policies apply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('status', 20)->default('tentative');
            $table->string('payment_status', 30)->default('unpaid');
            $table->string('source', 20)->default('front_desk');
            $table->foreignId('primary_guest_id')->constrained('guests')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('travel_agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->restrictOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2);
            $table->foreignId('deposit_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('deposit_percent', 5, 2)->default(0);
            $table->decimal('deposit_required', 15, 2)->default(0);
            $table->timestamp('deposit_due_at')->nullable();
            $table->boolean('auto_cancel_unpaid')->default(false);
            $table->unsignedBigInteger('deposit_override_by')->nullable();
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2);
            $table->date('balance_due_on')->nullable();
            $table->foreignId('cancellation_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('promo_code', 30)->nullable();
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->text('special_requests')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('cancellation_fee', 15, 2)->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'property_id', 'status', 'check_in']);
            $table->index(['tenant_id', 'primary_guest_id']);
        });

        Schema::create('reservation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->foreignId('cottage_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('room_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cottage_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->restrictOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->string('status', 20)->default('tentative');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('meal_component', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'reservation_id']);
        });

        Schema::create('reservation_item_nights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_item_id')->constrained()->cascadeOnDelete();
            $table->date('stay_date');
            $table->decimal('base_rate', 15, 2);
            $table->decimal('extra_person_amount', 15, 2)->default(0);
            $table->decimal('meal_amount', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('rate_source', 20)->nullable();
            $table->timestamp('posted_to_folio_at')->nullable();
            $table->timestamps();

            $table->unique(['reservation_item_id', 'stay_date']);
            $table->index(['tenant_id', 'property_id', 'stay_date']);
        });

        Schema::create('reservation_guests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'guest_id']);
        });

        Schema::table('inventory_locks', function (Blueprint $table): void {
            $table->foreign('reservation_id')->references('id')->on('reservations')->cascadeOnDelete();
            $table->foreign('reservation_item_id')->references('id')->on('reservation_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_locks', function (Blueprint $table): void {
            $table->dropForeign(['reservation_id']);
            $table->dropForeign(['reservation_item_id']);
        });
        Schema::dropIfExists('reservation_guests');
        Schema::dropIfExists('reservation_item_nights');
        Schema::dropIfExists('reservation_items');
        Schema::dropIfExists('reservations');
    }
};
