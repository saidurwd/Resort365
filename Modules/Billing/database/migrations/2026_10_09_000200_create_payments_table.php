<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments (ARCHITECTURE §8.3), each with a gap-free receipt number. Amounts are DECIMAL(15,2) in
 * the payment's currency; base_amount is in the property's currency. folio_id and cash_account_id
 * have no foreign keys yet: folios arrive in Step 2.1 and the chart of accounts in Phase 4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('receipt_no', 30);
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('folio_id')->nullable();
            $table->string('payment_type', 20);
            $table->string('method', 30);
            $table->decimal('amount', 15, 2);
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->decimal('base_amount', 15, 2);
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_txn_id', 100)->nullable();
            $table->string('status', 20)->default('succeeded');
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at');
            $table->unsignedBigInteger('cash_account_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_no']);
            $table->index(['tenant_id', 'reservation_id']);
            $table->index(['tenant_id', 'property_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
