<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The covers each stay's meal plan included on a business date, kept at the night audit (Step 3.8) so the
 * meal-plan report can compare meals included with meals taken for past days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_entitlement_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->unsignedBigInteger('reservation_id');
            $table->string('meal_period', 20);
            $table->unsignedSmallInteger('covers');
            $table->timestamps();

            $table->unique(['tenant_id', 'business_date', 'reservation_id', 'meal_period'], 'meal_entitlement_snapshots_unique');
            $table->index(['tenant_id', 'property_id', 'business_date'], 'meal_entitlement_snapshots_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_entitlement_snapshots');
    }
};
