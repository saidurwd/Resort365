<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS sessions (ARCHITECTURE §5.10.11): a cashier's cash drawer on one terminal for a business date,
 * opened with a float and closed with a count (open_terminal_id is set only while open: one open
 * session per terminal); and manager approvals (§3.3 rule 6): one action approved by a manager's
 * PIN on a terminal, used once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pos_terminal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('permission', 100);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'created_at']);
        });

        Schema::create('pos_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_terminal_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('opening_float', 15, 2);
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('cash_received', 15, 2)->nullable();
            $table->decimal('cash_refunded', 15, 2)->nullable();
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('counted_cash', 15, 2)->nullable();
            $table->decimal('cash_variance', 15, 2)->nullable();
            $table->string('variance_reason', 500)->nullable();
            $table->json('denominations')->nullable();
            $table->foreignId('manager_approval_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('open_terminal_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'open_terminal_id']);
            $table->index(['tenant_id', 'outlet_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
        Schema::dropIfExists('manager_approvals');
    }
};
