<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Night audit (ARCHITECTURE §5.7): one row per property and business date (the unique index makes
 * a second audit of the same date impossible), with what each step did; and the daily statistics
 * snapshot it takes (occupancy, ADR, RevPAR, revenue, takings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('night_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->string('status', 20);
            $table->string('trigger', 20);
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('nights_posted')->default(0);
            $table->unsignedInteger('no_shows')->default(0);
            $table->unsignedInteger('holds_released')->default(0);
            $table->json('issues')->nullable();
            $table->json('steps')->nullable();
            $table->string('error', 1000)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'property_id', 'business_date']);
        });

        Schema::create('daily_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('rooms_total');
            $table->unsignedInteger('rooms_out_of_order');
            $table->unsignedInteger('rooms_blocked');
            $table->unsignedInteger('rooms_available');
            $table->unsignedInteger('rooms_occupied');
            $table->decimal('occupancy_percent', 5, 2);
            $table->decimal('adr', 15, 2);
            $table->decimal('revpar', 15, 2);
            $table->decimal('room_revenue', 15, 2);
            $table->decimal('package_meal_revenue', 15, 2);
            $table->decimal('room_tax', 15, 2);
            $table->decimal('charges_total', 15, 2);
            $table->decimal('received_total', 15, 2);
            $table->decimal('refunded_total', 15, 2);
            $table->unsignedInteger('adults');
            $table->unsignedInteger('children');
            $table->unsignedInteger('arrivals');
            $table->unsignedInteger('departures');
            $table->unsignedInteger('no_shows');
            $table->unsignedInteger('fnb_covers')->default(0);
            $table->decimal('fnb_sales', 15, 2)->default(0);
            $table->json('takings');
            $table->timestamps();

            $table->unique(['tenant_id', 'property_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_statistics');
        Schema::dropIfExists('night_audits');
    }
};
