<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online payments (Step 8.3): each property's payment gateways (credentials encrypted), each attempt to pay a booking online, and
 * the gateway log (every callback, notification, refund and start, with whether its signature was valid). One gateway per provider
 * and property is kept by SaveGateway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('mode', 10)->default('sandbox');
            $table->text('credentials')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'provider']);
        });

        Schema::create('online_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->foreignId('payment_gateway_id')->constrained()->restrictOnDelete();
            $table->string('provider', 20);
            $table->unsignedBigInteger('reservation_id');
            $table->string('purpose', 10);
            $table->decimal('amount', 15, 2);
            $table->char('currency_code', 3);
            $table->string('status', 15)->default('pending');
            $table->string('gateway_ref', 150)->nullable();
            $table->string('transaction_id', 150)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('refunded_amount', 15, 2)->default(0);
            $table->string('return_url', 1000)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'uuid']);
            $table->index(['tenant_id', 'reservation_id']);
            $table->index(['tenant_id', 'provider', 'gateway_ref']);
        });

        Schema::create('gateway_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('online_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20);
            $table->string('kind', 20);
            $table->string('event_id', 191)->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->string('outcome', 30)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider', 'event_id']);
            $table->index(['tenant_id', 'online_payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_events');
        Schema::dropIfExists('online_payments');
        Schema::dropIfExists('payment_gateways');
    }
};
