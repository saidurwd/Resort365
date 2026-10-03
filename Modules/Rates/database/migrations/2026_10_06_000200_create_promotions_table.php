<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotions of a property (ARCHITECTURE §5.5): with a promo code, or automatic (code null) such as
 * long-stay discounts. Empty conditions mean "any". rate_plan_ids / unit_keys (e.g. "room_type:4")
 * limit the scope. times_used is counted when bookings use the promotion (Step 1.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 15, 2);
            $table->date('stay_from')->nullable();
            $table->date('stay_to')->nullable();
            $table->date('book_from')->nullable();
            $table->date('book_to')->nullable();
            $table->unsignedSmallInteger('min_nights')->nullable();
            $table->unsignedSmallInteger('max_nights')->nullable();
            $table->unsignedSmallInteger('min_advance_days')->nullable();
            $table->json('rate_plan_ids')->nullable();
            $table->json('unit_keys')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
            $table->index(['tenant_id', 'property_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
