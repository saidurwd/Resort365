<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave management (Step 6.3, ARCHITECTURE §5.15).
 *
 * - leave_types and leave_policies (one policy per type) are tenant-wide set-up data.
 * - leave_balances hold one employee's year of one leave type: entitled so far, carried forward, adjusted by HR, used and
 *   pending (reserved by requests waiting for approval). Available = entitled + carried + adjusted - used - pending.
 * - leave_requests are property-level (the employee's property when asked); `charged_dates` are the days charged and
 *   `days_by_year` how many fall in each calendar year.
 * - rosters get the approved leave that replaced a shift (leave_request_id, replaced_shift_id, leave_created).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->boolean('is_paid')->default(true);
            $table->boolean('has_balance')->default(true);
            $table->boolean('counts_non_working_days')->default(false);
            $table->string('gender', 10)->nullable();
            $table->string('color', 20)->default('primary');
            $table->unsignedSmallInteger('document_after_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'code']);
        });

        Schema::create('leave_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('annual_days', 5, 1)->default(0);
            $table->string('accrual', 10)->default('yearly');
            $table->decimal('carry_forward_max', 5, 1)->default(0);
            $table->unsignedSmallInteger('min_service_months')->default(0);
            $table->json('employment_types')->nullable();
            $table->unsignedSmallInteger('max_consecutive_days')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });

        Schema::create('leave_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('entitled', 6, 1)->default(0);
            $table->decimal('carried_forward', 6, 1)->default(0);
            $table->decimal('adjusted', 6, 1)->default(0);
            $table->decimal('used', 6, 1)->default(0);
            $table->decimal('pending', 6, 1)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
            $table->index(['tenant_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('request_no', 30);
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 1);
            $table->string('half_day', 10)->nullable();
            $table->json('charged_dates');
            $table->json('days_by_year');
            $table->text('reason')->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'request_no']);
            $table->index(['tenant_id', 'property_id', 'status']);
            $table->index(['tenant_id', 'employee_id', 'from_date']);
        });

        Schema::table('rosters', function (Blueprint $table): void {
            $table->foreignId('leave_request_id')->nullable()->after('shift_id')->constrained('leave_requests')->nullOnDelete();
            $table->foreignId('replaced_shift_id')->nullable()->after('leave_request_id')->constrained('shifts')->nullOnDelete();
            $table->boolean('leave_created')->default(false)->after('replaced_shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('rosters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('leave_request_id');
            $table->dropConstrainedForeignId('replaced_shift_id');
            $table->dropColumn('leave_created');
        });
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
    }
};
