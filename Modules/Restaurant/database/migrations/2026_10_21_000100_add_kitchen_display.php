<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kitchen display (ARCHITECTURE §5.10.6, §10.3): a station's display signs in with a device token
 * (stored as a SHA-256 hash, like POS terminals); tickets record when the kitchen started them, had
 * them ready and bumped them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchen_stations', function (Blueprint $table): void {
            $table->string('display_token', 64)->nullable()->after('printer_id');
        });

        Schema::table('kots', function (Blueprint $table): void {
            $table->timestamp('started_at')->nullable()->after('fired_at');
            $table->timestamp('ready_at')->nullable()->after('started_at');
            $table->timestamp('done_at')->nullable()->after('ready_at');
        });
    }

    public function down(): void
    {
        Schema::table('kots', function (Blueprint $table): void {
            $table->dropColumn(['started_at', 'ready_at', 'done_at']);
        });

        Schema::table('kitchen_stations', function (Blueprint $table): void {
            $table->dropColumn('display_token');
        });
    }
};
