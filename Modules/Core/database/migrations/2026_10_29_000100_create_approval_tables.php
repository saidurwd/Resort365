<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The approval workflow engine (Step 5.1): a workflow per document type with steps (level, label, the permission an
 * approver needs, the amount from which the step applies), requests with the steps that apply frozen on them, the
 * decisions taken, and delegations of approval rights.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 60);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'document_type']);
        });

        Schema::create('approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->string('label', 100);
            $table->string('permission', 100);
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['approval_workflow_id', 'level']);
        });

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->unsignedBigInteger('approval_workflow_id')->nullable();
            $table->string('document_type', 60);
            $table->string('reference', 190);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->json('steps');
            $table->unsignedSmallInteger('current_level');
            $table->string('status', 12)->default('pending');
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'document_type', 'subject_type', 'subject_id'], 'approval_requests_subject_index');
            $table->index(['tenant_id', 'requested_by']);
        });

        Schema::create('approval_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->nullable();
            $table->string('action', 12);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('on_behalf_of')->nullable();
            $table->string('comment', 500)->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['tenant_id', 'approval_request_id']);
        });

        Schema::create('approval_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('delegate_user_id');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index(['tenant_id', 'delegate_user_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
