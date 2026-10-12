<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendors, purchase requisitions, RFQs with quotations, and purchase orders (Step 5.5). Vendors are shared by the
 * tenant's properties; documents belong to one property. Unit prices keep four decimals, totals two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('tax_id', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 300)->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('status', 10)->default('active');
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_name', 100)->nullable();
            $table->string('bank_account_no', 50)->nullable();
            $table->string('bank_branch', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('vendor_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('role', 80)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'vendor_id']);
        });

        Schema::create('vendor_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('vendor_sku', 60)->nullable();
            $table->decimal('price', 15, 4);
            $table->decimal('min_quantity', 15, 4)->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'vendor_id', 'item_id']);
            $table->index(['tenant_id', 'item_id']);
        });

        Schema::create('purchase_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('requisition_no', 30);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->date('needed_on')->nullable();
            $table->string('status', 12)->default('pending');
            $table->string('source', 10)->default('manual');
            $table->string('notes', 300)->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'requisition_no']);
            $table->index(['tenant_id', 'property_id', 'status']);
        });

        Schema::create('purchase_requisition_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('estimated_unit_cost', 15, 4)->default(0);
            $table->string('notes', 200)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_requisition_id'], 'pr_lines_tenant_requisition_index');
        });

        Schema::create('rfqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('rfq_no', 30);
            $table->foreignId('purchase_requisition_id')->constrained()->restrictOnDelete();
            $table->string('status', 10)->default('draft');
            $table->date('due_on')->nullable();
            $table->string('notes', 300)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'rfq_no']);
            $table->index(['tenant_id', 'property_id', 'status']);
        });

        Schema::create('rfq_vendors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['rfq_id', 'vendor_id']);
            $table->index(['tenant_id', 'rfq_id']);
        });

        Schema::create('vendor_quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('reference', 60)->nullable();
            $table->date('quoted_on');
            $table->date('valid_until')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 10)->default('received');
            $table->string('notes', 300)->nullable();
            $table->timestamps();

            $table->unique(['rfq_id', 'vendor_id']);
            $table->index(['tenant_id', 'rfq_id']);
        });

        Schema::create('vendor_quotation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->unsignedSmallInteger('lead_days')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'vendor_quotation_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('order_no', 30);
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_requisition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('status', 12)->default('draft');
            $table->date('ordered_on');
            $table->date('expected_on')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('notes', 300)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to', 150)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_no']);
            $table->index(['tenant_id', 'property_id', 'status']);
            $table->index(['tenant_id', 'vendor_id']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->unsignedBigInteger('tax_category_id')->nullable();
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->decimal('received_quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_order_id']);
        });
    }

    public function down(): void
    {
        foreach (['purchase_order_lines', 'purchase_orders', 'vendor_quotation_lines', 'vendor_quotations', 'rfq_vendors', 'rfqs', 'purchase_requisition_lines', 'purchase_requisitions', 'vendor_items', 'vendor_contacts', 'vendors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
