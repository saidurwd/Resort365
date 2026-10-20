<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll runs, payslips and loans (Step 6.5, ARCHITECTURE §5.16).
 *
 * - A run is one property's month (unique): draft → calculated → pending approval → approved → paid. From approval the run and
 *   its payslips are locked.
 * - A payslip is one employee's month, with a snapshot of who they were and what they were paid; its lines are the components.
 * - Loans and salary advances are paid out once and recovered by installments, one per month, deducted by the payroll run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('run_no', 30);
            $table->date('period');
            $table->string('status', 20);
            $table->json('include_annual')->nullable();
            $table->json('warnings')->nullable();
            $table->unsignedInteger('employees_count')->default(0);
            $table->decimal('gross_total', 15, 2)->default(0);
            $table->decimal('deductions_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('net_total', 15, 2)->default(0);
            $table->decimal('employer_total', 15, 2)->default(0);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->string('notes', 300)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'period']);
            $table->unique(['tenant_id', 'run_no']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('payslips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('employee_no', 30);
            $table->string('employee_name', 200);
            $table->string('employee_email', 200)->nullable();
            $table->string('designation', 100);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('department_name', 100)->nullable();
            $table->string('structure_name', 100);
            $table->decimal('basic', 15, 2);
            $table->unsignedTinyInteger('days_in_month');
            $table->unsignedTinyInteger('days_employed');
            $table->decimal('pay_fraction', 7, 4)->default(1);
            $table->decimal('absent_days', 5, 1)->default(0);
            $table->decimal('unpaid_leave_days', 5, 1)->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->decimal('gross', 15, 2);
            $table->decimal('deductions', 15, 2);
            $table->decimal('tax', 15, 2);
            $table->decimal('net', 15, 2);
            $table->decimal('employer_cost', 15, 2)->default(0);
            $table->json('installment_ids')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['tenant_id', 'property_id']);
            $table->index(['tenant_id', 'employee_id']);
        });

        Schema::create('payslip_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->boolean('is_taxable')->default(false);
            $table->decimal('amount', 15, 2);
            $table->string('source', 20);
            $table->string('note', 300)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'payslip_id']);
        });

        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('loan_no', 30);
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->decimal('principal', 15, 2);
            $table->unsignedSmallInteger('installments_count');
            $table->date('first_month');
            $table->date('disbursed_on');
            $table->string('payment_method', 20);
            $table->string('reason', 300)->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'loan_no']);
            $table->index(['tenant_id', 'property_id', 'status']);
            $table->index(['tenant_id', 'employee_id']);
        });

        Schema::create('loan_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_month');
            $table->decimal('amount', 15, 2);
            $table->string('status', 20);
            $table->unsignedBigInteger('payslip_id')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'sequence']);
            $table->index(['tenant_id', 'status', 'due_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
    }
};
