<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shifts, rosters, attendance and holidays (Step 6.2, ARCHITECTURE §5.15).
 *
 * - shifts are tenant-wide; an end time at or before the start time means the shift runs past midnight.
 * - rosters say which shift an employee works on a date (no shift = day off); attendance holds the punches and the
 *   calculated late, early-leave, overtime and worked minutes. Both are property-level and unique per employee and date.
 * - times are wall-clock times in the property's timezone; a night shift's attendance belongs to the date it started.
 * - holidays are per property; repeats_yearly matches the month and day every year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_after_minutes')->default(0);
            $table->string('color', 20)->default('primary');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'code']);
        });

        Schema::create('rosters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['tenant_id', 'property_id', 'work_date']);
        });

        Schema::create('attendance_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('filename', 200);
            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('days_imported')->default(0);
            $table->unsignedInteger('days_skipped')->default(0);
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'property_id']);
        });

        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('status', 20);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->string('source', 10)->default('manual');
            $table->foreignId('attendance_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 300)->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['tenant_id', 'property_id', 'work_date']);
            $table->index(['tenant_id', 'work_date', 'status']);
        });

        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->date('holiday_date');
            $table->boolean('is_paid')->default(true);
            $table->boolean('repeats_yearly')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'holiday_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('attendance_imports');
        Schema::dropIfExists('rosters');
        Schema::dropIfExists('shifts');
    }
};
