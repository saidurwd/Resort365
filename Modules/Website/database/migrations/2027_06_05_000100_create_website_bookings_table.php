<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings made on the public website (Step 8.2b): which booking, whether the guest chose to pay the deposit or the full amount and
 * how much that is now (Step 8.3 takes it), and the email and address the booking came from (for the abuse limits). The sites may
 * be embedded in other websites, from the origins listed in `embed_origins`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_site_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('reservation_id');
            $table->string('reservation_code', 30);
            $table->string('pay_choice', 10);
            $table->decimal('amount_due_now', 15, 2);
            $table->char('currency_code', 3);
            $table->string('email');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reservation_id']);
            $table->unique(['tenant_id', 'reservation_code']);
            $table->index(['tenant_id', 'email', 'created_at']);
            $table->index(['tenant_id', 'ip_address', 'created_at']);
        });

        Schema::table('website_sites', function (Blueprint $table): void {
            $table->text('embed_origins')->nullable()->after('instagram_url');
        });
    }

    public function down(): void
    {
        Schema::table('website_sites', function (Blueprint $table): void {
            $table->dropColumn('embed_origins');
        });
        Schema::dropIfExists('website_bookings');
    }
};
