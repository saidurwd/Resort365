<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Housekeeping & maintenance (ARCHITECTURE §5.11): room status history, cleaning tasks, out-of-order
 * and out-of-service blocks (OOO blocks also lock inventory, through Reservation's RoomBlocks),
 * maintenance work orders and preventive schedules, and the lost & found register.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->string('reason', 190);
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'room_id', 'created_at']);
        });

        Schema::create('housekeeping_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->string('type', 20);
            $table->string('status', 20)->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->unsignedBigInteger('inspected_by')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'room_id', 'business_date', 'type']);
            $table->index(['tenant_id', 'property_id', 'business_date', 'status'], 'housekeeping_tasks_property_day_status_index');
            $table->index(['tenant_id', 'assigned_to', 'status']);
        });

        Schema::create('room_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->date('from_date');
            $table->date('to_date');
            $table->string('reason', 190);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('ended_by')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'status', 'from_date']);
            $table->index(['tenant_id', 'room_id']);
        });

        Schema::create('maintenance_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->string('category', 20);
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location', 150)->nullable();
            $table->unsignedSmallInteger('interval_days');
            $table->date('next_due_on');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'is_active', 'next_due_on'], 'maintenance_schedules_due_index');
        });

        Schema::create('maintenance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location', 150)->nullable();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('category', 20);
            $table->string('priority', 20);
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('maintenance_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('labour_cost', 15, 2)->default(0);
            $table->decimal('parts_cost', 15, 2)->default(0);
            $table->string('resolution', 1000)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'status']);
            $table->index(['tenant_id', 'assigned_to', 'status']);
            $table->unique(['tenant_id', 'maintenance_schedule_id', 'due_on'], 'maintenance_requests_schedule_due_unique');
        });

        Schema::create('lost_found_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->date('found_on');
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('found_at', 150);
            $table->string('description', 500);
            $table->unsignedBigInteger('found_by')->nullable();
            $table->string('stored_at', 150)->nullable();
            $table->string('status', 20)->default('stored');
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('claimed_by_name', 150)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'status', 'found_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_found_items');
        Schema::dropIfExists('maintenance_requests');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('room_blocks');
        Schema::dropIfExists('housekeeping_tasks');
        Schema::dropIfExists('room_status_logs');
    }
};
