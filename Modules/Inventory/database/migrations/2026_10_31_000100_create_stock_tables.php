<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stock ledger (Step 5.3): immutable movements, a balance per item and store with its weighted-average cost
 * (kept in step with every movement), and two-step transfers between the stores of a property. Quantities are in the
 * item's base unit with four decimals; costs per unit have four decimals, totals two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('avg_cost', 15, 4)->default(0);
            $table->unsignedBigInteger('last_movement_id')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'item_id']);
            $table->index(['tenant_id', 'property_id']);
        });

        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('transfer_no', 30);
            $table->foreignId('from_store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('to_store_id')->constrained('stores')->restrictOnDelete();
            $table->string('status', 12)->default('dispatched');
            $table->string('notes', 300)->nullable();
            $table->timestamp('dispatched_at');
            $table->unsignedBigInteger('dispatched_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'transfer_no']);
            $table->index(['tenant_id', 'property_id', 'status']);
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->timestamps();

            $table->unique(['stock_transfer_id', 'item_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('movement_type', 20);
            $table->date('movement_date');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('total_cost', 15, 2);
            $table->decimal('quantity_after', 15, 4);
            $table->decimal('avg_cost_after', 15, 4);
            $table->string('reason', 30)->nullable();
            $table->string('notes', 300)->nullable();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_event', 60)->nullable();
            $table->string('idempotency_key', 120)->nullable();
            $table->unsignedBigInteger('reverses_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key'], 'stock_movements_idempotency_unique');
            $table->unique(['tenant_id', 'reverses_id'], 'stock_movements_reverses_unique');
            $table->index(['tenant_id', 'store_id', 'item_id', 'id'], 'stock_movements_ledger_index');
            $table->index(['tenant_id', 'property_id', 'movement_date']);
            $table->index(['tenant_id', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_balances');
    }
};
