<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotes (ARCHITECTURE §5.6): a saved price proposal for a guest, emailed and later converted into
 * a reservation at the quoted prices. Items and nights mirror reservation_items /
 * reservation_item_nights, so the conversion books exactly what was quoted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('status', 20)->default('draft');
            $table->string('source', 20)->default('front_desk');
            $table->foreignId('guest_id')->constrained('guests')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('travel_agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->restrictOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->char('currency_code', 3);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2);
            $table->decimal('deposit_percent', 5, 2)->default(0);
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->string('promo_code', 30)->nullable();
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->date('valid_until');
            $table->text('special_requests')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'property_id', 'status', 'check_in']);
        });

        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->foreignId('cottage_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('room_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cottage_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->restrictOnDelete();
            $table->string('unit_key', 40);
            $table->string('label');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('meal_component', 15, 2)->default(0);
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->string('promotion_code', 30)->nullable();
            $table->string('promotion_name')->nullable();
            $table->decimal('promotion_amount', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'quote_id']);
        });

        Schema::create('quote_item_nights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('quote_item_id')->constrained()->cascadeOnDelete();
            $table->date('stay_date');
            $table->decimal('base_rate', 15, 2);
            $table->decimal('extra_person_amount', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('rate_source', 20)->nullable();
            $table->string('season_name')->nullable();
            $table->timestamps();

            $table->unique(['quote_item_id', 'stay_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_item_nights');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
