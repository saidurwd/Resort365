<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Group bookings (ARCHITECTURE §5.6): a booking with a group name is a group — many rooms, a
 * rooming list (reservation_guests per item) and a master folio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->string('group_name', 150)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn('group_name');
        });
    }
};
