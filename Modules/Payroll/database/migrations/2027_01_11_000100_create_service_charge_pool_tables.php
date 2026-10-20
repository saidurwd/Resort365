<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service charge and tips pools (Step 6.6, ARCHITECTURE §5.16).
 *
 * - A pool is one property's month for one kind (service charge or tips): what was collected, the share that goes to staff, the
 *   rule, and the status. It is finalised for payroll and distributed when the payroll run is approved.
 * - An allocation is one employee's share of a pool; the payslip that paid it is linked when the run is calculated.
 * - A participant says whether an employee takes part in the pools and how many points they hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_charge_pools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->date('period');
            $table->string('rule', 20);
            $table->decimal('collected', 15, 2)->default(0);
            $table->decimal('staff_percent', 5, 2)->default(100);
            $table->decimal('distributable', 15, 2)->default(0);
            $table->unsignedInteger('employees_count')->default(0);
            $table->string('status', 20);
            $table->unsignedBigInteger('payroll_run_id')->nullable();
            $table->timestamp('finalised_at')->nullable();
            $table->unsignedBigInteger('finalised_by')->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'period', 'kind']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('service_charge_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_charge_pool_id')->constrained('service_charge_pools')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('employee_no', 30);
            $table->string('employee_name', 200);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->decimal('weight', 10, 4);
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('payslip_id')->nullable();
            $table->timestamps();

            $table->unique(['service_charge_pool_id', 'employee_id'], 'sc_alloc_pool_employee');
            $table->index(['tenant_id', 'employee_id']);
        });

        Schema::create('pool_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_eligible')->default(true);
            $table->decimal('points', 6, 2)->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pool_participants');
        Schema::dropIfExists('service_charge_allocations');
        Schema::dropIfExists('service_charge_pools');
    }
};
