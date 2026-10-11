<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store requisitions, issues and physical counts (Step 5.4), the department charged on a movement, and the class of an
 * item category (it decides which ledger accounts an issue or adjustment posts to).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('department_id')->nullable()->after('store_id');
        });

        Schema::table('item_categories', function (Blueprint $table): void {
            $table->string('account_class', 15)->nullable()->after('name');
        });

        Schema::create('stock_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('requisition_no', 30);
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('department_id');
            $table->date('needed_on')->nullable();
            $table->string('status', 12)->default('pending');
            $table->string('notes', 300)->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->unsignedBigInteger('issue_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'requisition_no']);
            $table->index(['tenant_id', 'property_id', 'status']);
        });

        Schema::create('stock_requisition_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('issued_quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['stock_requisition_id', 'item_id']);
        });

        Schema::create('stock_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('issue_no', 30);
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('requisition_id')->nullable();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reason', 60)->nullable();
            $table->string('notes', 300)->nullable();
            $table->date('issued_on');
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'issue_no']);
            $table->index(['tenant_id', 'property_id', 'issued_on']);
            $table->index(['tenant_id', 'source_type', 'source_id']);
        });

        Schema::create('stock_issue_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('total_cost', 15, 2);
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->timestamps();

            $table->unique(['stock_issue_id', 'item_id']);
        });

        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('count_no', 30);
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('item_category_id')->nullable();
            $table->string('status', 12)->default('counting');
            $table->boolean('blind')->default(false);
            $table->string('notes', 300)->nullable();
            $table->timestamp('started_at');
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'count_no']);
            $table->index(['tenant_id', 'store_id', 'status']);
        });

        Schema::create('stock_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('system_quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->unsignedBigInteger('snapshot_movement_id')->default(0);
            $table->decimal('counted_quantity', 15, 4)->nullable();
            $table->decimal('variance_quantity', 15, 4)->nullable();
            $table->unsignedBigInteger('adjustment_movement_id')->nullable();
            $table->timestamps();

            $table->unique(['stock_count_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_issue_lines');
        Schema::dropIfExists('stock_issues');
        Schema::dropIfExists('stock_requisition_lines');
        Schema::dropIfExists('stock_requisitions');
        Schema::table('item_categories', function (Blueprint $table): void {
            $table->dropColumn('account_class');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn('department_id');
        });
    }
};
