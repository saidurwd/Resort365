<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users belong to exactly one tenant; email is unique per tenant (ARCHITECTURE §3.3).
 * Adds account status, preferences, login tracking and Fortify's TOTP 2FA columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->after('id')->constrained()->restrictOnDelete();

            $table->dropUnique('users_email_unique');
            $table->unique(['tenant_id', 'email']);

            $table->string('password')->nullable()->change();
            $table->string('status', 20)->default('active')->after('password');
            $table->string('locale', 10)->default('en')->after('status');
            $table->string('theme', 10)->nullable()->after('locale');

            $table->text('two_factor_secret')->nullable()->after('remember_token');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn([
                'status', 'locale', 'theme', 'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at', 'last_login_at', 'last_login_ip', 'invited_at', 'deactivated_at',
            ]);
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique('email');
        });
    }
};
