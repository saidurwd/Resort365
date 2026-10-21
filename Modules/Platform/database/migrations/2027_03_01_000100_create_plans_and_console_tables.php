<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plans, announcements, the console's audit log and impersonation sessions (Step 7.3, ARCHITECTURE §5.2). All central: none of
 * these belong to a tenant. A tenant points at its plan (no plan means no limits and every module).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80);
            $table->string('description', 300)->nullable();
            $table->decimal('monthly_price', 15, 2)->default(0);
            $table->decimal('yearly_price', 15, 2)->default(0);
            $table->char('currency_code', 3)->default('USD');
            $table->unsignedInteger('max_properties')->nullable();
            $table->unsignedInteger('max_rooms')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('storage_gb')->nullable();
            $table->json('modules');
            $table->boolean('api_access')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignId('plan_id')->nullable()->after('status')->constrained('plans')->nullOnDelete();
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            $table->string('level', 10)->default('info');
            $table->string('audience', 10)->default('all');
            $table->json('plan_ids')->nullable();
            $table->json('tenant_ids')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('platform_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('platform_admin_id')->nullable();
            $table->string('admin_name', 120);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('action', 60);
            $table->string('summary', 300);
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('impersonation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_admin_id')->constrained('platform_admins')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('user_name', 120);
            $table->string('reason', 300);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_sessions');
        Schema::dropIfExists('platform_audit_log');
        Schema::dropIfExists('announcements');
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plan_id');
        });
        Schema::dropIfExists('plans');
    }
};
