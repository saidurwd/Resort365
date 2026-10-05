<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charge to room, city ledger and meal plans (ARCHITECTURE §5.10.8–5.10.9, §8.4): a payment may go to an
 * in-house guest's folio (with the folio line it made and an optional signature) or a company's
 * account; meal-plan redemptions record the covers a booking took per meal period, and the order lines
 * they cover go on the bill at nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('reservation_id')->nullable()->after('reference');
            $table->unsignedBigInteger('folio_id')->nullable()->after('reservation_id');
            $table->unsignedBigInteger('folio_line_id')->nullable()->after('folio_id');
            $table->unsignedBigInteger('company_id')->nullable()->after('folio_line_id');
            $table->unsignedBigInteger('city_ledger_entry_id')->nullable()->after('company_id');
            $table->string('charged_to', 190)->nullable()->after('city_ledger_entry_id');
            $table->string('signature_path', 255)->nullable()->after('charged_to');
            $table->index(['tenant_id', 'reservation_id']);
        });

        Schema::create('package_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('reservation_id');
            $table->string('reservation_code', 30);
            $table->string('guest_name', 190);
            $table->date('business_date');
            $table->string('meal_period', 20);
            $table->unsignedSmallInteger('covers_adults');
            $table->unsignedSmallInteger('covers_children')->default(0);
            $table->unsignedSmallInteger('entitled')->default(0);
            $table->foreignId('pos_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_bill_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('cost_amount', 15, 2)->nullable();
            $table->foreignId('manager_approval_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'reservation_id', 'business_date', 'meal_period'], 'package_redemptions_entitlement_index');
            $table->index(['tenant_id', 'outlet_id', 'business_date']);
        });

        Schema::table('pos_order_lines', function (Blueprint $table): void {
            $table->foreignId('package_redemption_id')->nullable()->after('discount_approval_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_order_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('package_redemption_id');
        });

        Schema::dropIfExists('package_redemptions');

        Schema::table('pos_payments', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'reservation_id']);
            $table->dropColumn(['reservation_id', 'folio_id', 'folio_line_id', 'company_id', 'city_ledger_entry_id', 'charged_to', 'signature_path']);
        });
    }
};
