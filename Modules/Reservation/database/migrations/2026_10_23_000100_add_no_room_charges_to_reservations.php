<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "No room charges" (ARCHITECTURE §5.10.8): outlets may not charge this booking's folio (e.g. a travel
 * agent pays the room only, on strict terms).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->boolean('no_room_charges')->default(false)->after('group_name');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn('no_room_charges');
        });
    }
};
