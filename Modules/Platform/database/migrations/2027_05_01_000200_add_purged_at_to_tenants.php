<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a cancelled tenant's data was deleted after its retention period (Step 7.5). The tenant row stays as a tombstone for the
 * platform's own records (subscriptions, invoices, audit log), and its subdomain stays taken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('purged_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('purged_at');
        });
    }
};
