<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rate plans of a property (ARCHITECTURE §5.5). meal_adult_amount / meal_child_amount are the
 * meal component per person per night (the part of the rate that pays for included meals).
 * prices_include_tax says whether the plan's amounts include its tax category's taxes.
 * TODO(step-1.4): deposit_policy_id and cancellation_policy_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('meal_plan', 10)->default('EP');
            $table->decimal('meal_adult_amount', 15, 2)->default(0);
            $table->decimal('meal_child_amount', 15, 2)->default(0);
            $table->boolean('is_refundable')->default(true);
            $table->boolean('prices_include_tax')->default(false);
            $table->foreignId('tax_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->json('channels')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
            $table->index(['tenant_id', 'property_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_plans');
    }
};
