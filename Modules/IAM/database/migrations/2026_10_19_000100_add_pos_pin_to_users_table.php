<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The POS PIN (ARCHITECTURE §3.3 rule 6): a keyed hash (HMAC with the app key and the tenant), unique
 * per tenant so a PIN points to one person; used only on registered POS terminals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('pos_pin', 64)->nullable()->after('password');
            $table->timestamp('pos_pin_set_at')->nullable()->after('pos_pin');

            $table->unique(['tenant_id', 'pos_pin']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'pos_pin']);
            $table->dropColumn(['pos_pin', 'pos_pin_set_at']);
        });
    }
};
