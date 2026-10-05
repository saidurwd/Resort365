<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bills, discounts and payments (ARCHITECTURE §5.10.7, §8.4): item and bill discounts on the order; an
 * order printed as one bill or split into several (numbered per outlet, gap-free, prices and taxes
 * frozen on the bill lines); payments per bill in a POS session (refunds point at what they refund);
 * each role's maximum discount %.
 */
return new class extends Migration
{
    public function up(): void
    {
        $discount = function (Blueprint $table, string $after): void {
            $table->string('discount_type', 10)->nullable()->after($after);
            $table->decimal('discount_value', 15, 2)->nullable()->after('discount_type');
            $table->string('discount_reason', 300)->nullable()->after('discount_value');
            $table->unsignedBigInteger('discount_by')->nullable()->after('discount_reason');
            $table->foreignId('discount_approval_id')->nullable()->after('discount_by')->constrained('manager_approvals')->nullOnDelete();
        };

        Schema::table('pos_order_lines', fn (Blueprint $table) => $discount($table, 'line_total'));
        Schema::table('pos_orders', fn (Blueprint $table) => $discount($table, 'subtotal'));

        Schema::create('pos_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('bill_no', 30);
            $table->date('business_date');
            $table->string('split_label', 40)->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('service_charge', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2);
            $table->decimal('tip_total', 15, 2)->default(0);
            $table->decimal('paid_total', 15, 2)->default(0);
            $table->json('tax_breakdown')->nullable();
            $table->string('status', 20)->default('printed');
            $table->boolean('is_complimentary')->default(false);
            $table->string('comp_reason', 30)->nullable();
            $table->string('comp_note', 300)->nullable();
            $table->unsignedSmallInteger('print_count')->default(0);
            $table->unsignedSmallInteger('receipt_count')->default(0);
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->unsignedBigInteger('settled_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason', 300)->nullable();
            $table->foreignId('manager_approval_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'sequence']);
            $table->unique(['tenant_id', 'outlet_id', 'bill_no']);
            $table->index(['tenant_id', 'pos_order_id', 'status']);
            $table->index(['tenant_id', 'outlet_id', 'business_date']);
        });

        Schema::create('pos_bill_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_order_line_id')->constrained()->restrictOnDelete();
            $table->string('name_snapshot', 250);
            $table->decimal('quantity', 10, 3);
            $table->decimal('amount', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('gross', 15, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'pos_bill_id']);
        });

        Schema::create('pos_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_session_id')->nullable()->constrained()->nullOnDelete();
            $table->date('business_date');
            $table->string('method', 20);
            $table->decimal('amount', 15, 2);
            $table->decimal('tip', 15, 2)->default(0);
            $table->decimal('tendered', 15, 2)->nullable();
            $table->decimal('change_given', 15, 2)->default(0);
            $table->string('reference', 100)->nullable();
            $table->foreignId('refund_of_id')->nullable()->constrained('pos_payments')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'pos_bill_id']);
            $table->index(['tenant_id', 'pos_session_id', 'method']);
        });

        Schema::create('discount_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->decimal('max_percent', 5, 2);
            $table->timestamps();

            $table->unique(['tenant_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_limits');
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_bill_lines');
        Schema::dropIfExists('pos_bills');

        foreach (['pos_orders', 'pos_order_lines'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('discount_approval_id');
                $table->dropColumn(['discount_type', 'discount_value', 'discount_reason', 'discount_by']);
            });
        }
    }
};
