<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier shifts (ARCHITECTURE §5.9): a cashier's float, the cash taken and refunded in the shift,
 * the count at close and the variance. Payments taken while a shift is open belong to it, and
 * every payment records the business date it was taken on (the day's takings). Folio
 * room-night lines keep the meal component of the night's net (package split, night audit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->timestamp('opened_at');
            $table->decimal('opening_float', 15, 2);
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->decimal('cash_received', 15, 2)->nullable();
            $table->decimal('cash_refunded', 15, 2)->nullable();
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('counted_cash', 15, 2)->nullable();
            $table->decimal('cash_variance', 15, 2)->nullable();
            $table->string('variance_reason', 500)->nullable();
            $table->json('denominations')->nullable();
            $table->string('status', 20)->default('open');
            // Set while open: one open shift per cashier and property (NULL once closed).
            $table->unsignedBigInteger('open_user_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'property_id', 'open_user_id']);
            $table->index(['tenant_id', 'property_id', 'business_date']);
            $table->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->date('business_date')->nullable()->after('received_at');
            $table->foreignId('cashier_shift_id')->nullable()->after('business_date')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'property_id', 'business_date']);
        });

        Schema::table('folio_lines', function (Blueprint $table): void {
            $table->decimal('meal_amount', 15, 2)->default(0)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('folio_lines', function (Blueprint $table): void {
            $table->dropColumn('meal_amount');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'property_id', 'business_date']);
            $table->dropConstrainedForeignId('cashier_shift_id');
            $table->dropColumn('business_date');
        });
        Schema::dropIfExists('cashier_shifts');
    }
};
