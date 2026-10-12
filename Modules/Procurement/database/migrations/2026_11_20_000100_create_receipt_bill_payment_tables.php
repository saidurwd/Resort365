<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods receipts, vendor bills, vendor payments and purchase returns (Step 5.6). All belong to a property; money is
 * DECIMAL(15,2), quantities and unit prices keep four decimals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('receipt_no', 30);
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('store_id');
            $table->date('received_on');
            $table->string('delivery_note', 60)->nullable();
            $table->string('notes', 300)->nullable();
            $table->unsignedBigInteger('received_by');
            $table->decimal('total_value', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_no']);
            $table->index(['tenant_id', 'purchase_order_id']);
            $table->index(['tenant_id', 'property_id', 'received_on']);
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('accepted_quantity', 15, 4);
            $table->decimal('rejected_quantity', 15, 4)->default(0);
            $table->string('reject_reason', 150)->nullable();
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('value', 15, 2)->default(0);
            $table->decimal('returned_quantity', 15, 4)->default(0);
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'goods_receipt_id']);
            $table->index(['tenant_id', 'purchase_order_line_id']);
        });

        Schema::create('vendor_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('bill_no', 30);
            $table->string('vendor_reference', 60)->nullable();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10);
            $table->string('status', 10)->default('draft');
            $table->date('bill_date');
            $table->date('due_on');
            $table->char('currency', 3)->default('BDT');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->boolean('match_overridden')->default(false);
            $table->string('match_notes', 300)->nullable();
            $table->string('notes', 300)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'bill_no']);
            $table->index(['tenant_id', 'property_id', 'status']);
            $table->index(['tenant_id', 'vendor_id', 'status']);
            $table->index(['tenant_id', 'purchase_order_id']);
        });

        Schema::create('vendor_bill_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('description', 200)->nullable();
            $table->string('expense_class', 15)->nullable();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('unit_price', 15, 4);
            $table->unsignedBigInteger('tax_category_id')->nullable();
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->decimal('grni_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'vendor_bill_id']);
            $table->index(['tenant_id', 'purchase_order_line_id']);
        });

        Schema::create('vendor_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('payment_no', 30);
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('method', 15);
            $table->date('paid_on');
            $table->decimal('amount', 15, 2);
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->string('reference', 80)->nullable();
            $table->string('notes', 300)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->unique(['tenant_id', 'payment_no']);
            $table->index(['tenant_id', 'property_id', 'paid_on']);
            $table->index(['tenant_id', 'vendor_id']);
        });

        Schema::create('vendor_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_bill_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'vendor_payment_id'], 'vp_alloc_tenant_payment_index');
            $table->index(['tenant_id', 'vendor_bill_id'], 'vp_alloc_tenant_bill_index');
        });

        Schema::create('purchase_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('return_no', 30);
            $table->string('debit_note_no', 30);
            $table->foreignId('goods_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('store_id');
            $table->date('returned_on');
            $table->string('reason', 150);
            $table->string('notes', 300)->nullable();
            $table->decimal('total_value', 15, 2)->default(0);
            $table->decimal('tax_value', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->unique(['tenant_id', 'return_no']);
            $table->unique(['tenant_id', 'debit_note_no']);
            $table->index(['tenant_id', 'property_id', 'returned_on']);
            $table->index(['tenant_id', 'goods_receipt_id']);
        });

        Schema::create('purchase_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('value', 15, 2)->default(0);
            $table->decimal('grni_value', 15, 2)->default(0);
            $table->decimal('billed_value', 15, 2)->default(0);
            $table->decimal('tax_value', 15, 2)->default(0);
            $table->decimal('stock_cost', 15, 2)->default(0);
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_return_id'], 'pr_return_lines_tenant_return_index');
        });
    }

    public function down(): void
    {
        foreach (['purchase_return_lines', 'purchase_returns', 'vendor_payment_allocations', 'vendor_payments', 'vendor_bill_lines', 'vendor_bills', 'goods_receipt_lines', 'goods_receipts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
