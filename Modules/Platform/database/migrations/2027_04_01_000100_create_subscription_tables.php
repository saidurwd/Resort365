<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions, platform invoices and their payments (Step 7.4, ARCHITECTURE §5.2). All central: the platform bills its tenants. A
 * tenant also remembers why it was suspended, so paying an overdue invoice can lift a suspension for non-payment only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('billing_period', 10);
            $table->string('status', 12);
            $table->timestamp('trial_ends_at')->nullable();
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->string('cancel_reason', 300)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('gateway', 20)->default('manual');
            $table->string('gateway_reference', 100)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'current_period_end']);
        });

        Schema::create('platform_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10);
            $table->string('description', 200);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('issued_on');
            $table->date('due_on');
            $table->char('currency_code', 3);
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 300)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'issued_on']);
            $table->index(['status', 'due_on']);
        });

        Schema::create('platform_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->date('paid_on');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'paid_on']);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('suspended_for', 10)->nullable()->after('suspended_at');
            $table->timestamp('cancelled_at')->nullable()->after('suspended_for');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['suspended_for', 'cancelled_at']);
        });
        Schema::dropIfExists('platform_payments');
        Schema::dropIfExists('platform_invoices');
        Schema::dropIfExists('subscriptions');
    }
};
