<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exchange rates per tenant: 1 unit of base currency = rate units of quote currency, from effective_date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->date('effective_date');
            $table->string('source', 50)->nullable();
            $table->timestamps();

            $table->foreign('base_currency')->references('code')->on('currencies');
            $table->foreign('quote_currency')->references('code')->on('currencies');
            $table->unique(['tenant_id', 'base_currency', 'quote_currency', 'effective_date'], 'exchange_rates_pair_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
