<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prices (ARCHITECTURE §5.5, §8.3). rateable_type is "room_type" or "cottage_type" (the Property
 * module's morph aliases; no foreign key across modules). dow_mask: bit 1 = Monday … 64 = Sunday,
 * 127 = every day. season_id null = the base rate. One base rate per plan, unit and day set is
 * enforced by SaveRateSheet (NULLs are distinct in unique indexes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->string('rateable_type', 20);
            $table->unsignedBigInteger('rateable_id');
            $table->foreignId('season_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('dow_mask')->default(127);
            $table->decimal('amount', 15, 2);
            $table->decimal('extra_adult_amount', 15, 2)->default(0);
            $table->decimal('extra_child_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'rate_plan_id']);
            $table->index(['rate_plan_id', 'rateable_type', 'rateable_id', 'season_id']);
        });

        Schema::create('rate_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->string('rateable_type', 20);
            $table->unsignedBigInteger('rateable_id');
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['rate_plan_id', 'rateable_type', 'rateable_id', 'date'], 'rate_overrides_unit_date_unique');
            $table->index(['tenant_id', 'property_id', 'date']);
        });

        Schema::create('rate_restrictions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('rateable_type', 20)->nullable();
            $table->unsignedBigInteger('rateable_id')->nullable();
            $table->date('date');
            $table->unsignedTinyInteger('min_stay')->nullable();
            $table->unsignedTinyInteger('max_stay')->nullable();
            $table->boolean('closed_to_arrival')->default(false);
            $table->boolean('closed_to_departure')->default(false);
            $table->boolean('stop_sell')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_restrictions');
        Schema::dropIfExists('rate_overrides');
        Schema::dropIfExists('rates');
    }
};
