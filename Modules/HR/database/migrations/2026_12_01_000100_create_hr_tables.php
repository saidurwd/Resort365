<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organisation and employees (Step 6.1, ARCHITECTURE §5.15, §8.5).
 *
 * - designations and grades are tenant-wide set-up data; departments stay in Property.
 * - employees belong to one property. national_id, passport_no and bank_account_no are encrypted (text columns);
 *   national_id_hash is a keyed hash for duplicate checks.
 * - employee_histories is an append-only record of what happened to an employee (joined, confirmation, transfer,
 *   promotion, increment, disciplinary, exit); `details` holds the before and after values.
 * - Documents (contract, certificates) and the photo are attachments, so there is no documents table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'department_id']);
        });

        Schema::create('grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->unsignedSmallInteger('level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'level']);
        });

        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('employee_no', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->char('nationality_code', 2)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->json('address')->nullable();
            $table->json('emergency_contact')->nullable();
            $table->text('national_id')->nullable();
            $table->char('national_id_hash', 64)->nullable();
            $table->text('passport_no')->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_name', 100)->nullable();
            $table->text('bank_account_no')->nullable();
            $table->string('bank_branch', 100)->nullable();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('designation_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employment_type', 20);
            $table->date('joined_on');
            $table->date('probation_ends_on')->nullable();
            $table->date('confirmed_on')->nullable();
            $table->string('status', 20);
            $table->date('exited_on')->nullable();
            $table->string('exit_reason', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('nationality_code')->references('code')->on('countries');
            $table->unique(['tenant_id', 'employee_no']);
            $table->index(['tenant_id', 'property_id', 'status']);
            $table->index(['tenant_id', 'department_id']);
            $table->index(['tenant_id', 'manager_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'national_id_hash']);
            $table->index(['tenant_id', 'last_name', 'first_name']);
        });

        Schema::create('employee_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20);
            $table->date('effective_on');
            $table->json('details')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'effective_on'], 'employee_histories_employee_index');
            $table->index(['tenant_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_histories');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('designations');
    }
};
