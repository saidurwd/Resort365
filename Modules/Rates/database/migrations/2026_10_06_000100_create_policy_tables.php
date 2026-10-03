<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deposit and cancellation policies of a property (ARCHITECTURE §6.5, §8.3).
 *
 * - The deposit is negotiable per booking (Q7): default_percent is the suggestion, min/max are
 *   optional limits. Unpaid deposits are due within due_within_minutes (default 30).
 * - full_payment_within_hours: ask for full payment when arrival is closer than this.
 * - Cancellation tiers cover days before arrival [days_before_from, days_before_to] (to null =
 *   and more); the no-show charge is on the policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('percentage');
            $table->decimal('min_percent', 5, 2)->nullable();
            $table->decimal('default_percent', 5, 2)->default(30);
            $table->decimal('max_percent', 5, 2)->nullable();
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->unsignedInteger('due_within_minutes')->default(30);
            $table->boolean('auto_cancel_unpaid')->default(true);
            $table->string('balance_due_rule', 30)->default('at_check_in');
            $table->unsignedSmallInteger('balance_due_days')->nullable();
            $table->unsignedSmallInteger('full_payment_within_hours')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'property_id']);
        });

        Schema::create('cancellation_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('no_show_charge_type', 30)->nullable();
            $table->decimal('no_show_charge_value', 15, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'property_id']);
        });

        Schema::create('cancellation_policy_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cancellation_policy_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days_before_from');
            $table->unsignedSmallInteger('days_before_to')->nullable();
            $table->string('charge_type', 30);
            $table->decimal('charge_value', 15, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'cancellation_policy_id']);
        });

        Schema::table('rate_plans', function (Blueprint $table): void {
            $table->foreignId('deposit_policy_id')->nullable()->after('tax_category_id')->constrained()->nullOnDelete();
            $table->foreignId('cancellation_policy_id')->nullable()->after('deposit_policy_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rate_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancellation_policy_id');
            $table->dropConstrainedForeignId('deposit_policy_id');
        });
        Schema::dropIfExists('cancellation_policy_rules');
        Schema::dropIfExists('cancellation_policies');
        Schema::dropIfExists('deposit_policies');
    }
};
