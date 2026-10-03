<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest profiles, shared by all the tenant's properties (ARCHITECTURE §5.8, §8.3).
 *
 * - id_number is encrypted (Laravel `encrypted` cast); id_number_hash is a keyed hash of the
 *   normalised type and number, used to find duplicates without storing the number in clear.
 * - phone is stored in international format (+8801711000000).
 * - merged_into_id points a merged (soft-deleted) profile at the one that was kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title', 20)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->char('nationality_code', 2)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('id_type', 30)->nullable();
            $table->text('id_number')->nullable();
            $table->char('id_number_hash', 64)->nullable();
            $table->date('id_expiry')->nullable();
            $table->json('address')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vip_level', 20)->default('none');
            $table->boolean('is_blacklisted')->default(false);
            $table->text('blacklist_reason')->nullable();
            $table->timestamp('blacklisted_at')->nullable();
            $table->unsignedBigInteger('blacklisted_by')->nullable();
            $table->json('preferences')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('merged_into_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('nationality_code')->references('code')->on('countries');
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'id_number_hash']);
            $table->index(['tenant_id', 'last_name', 'first_name']);
            $table->index(['tenant_id', 'first_name']);
            $table->index(['tenant_id', 'is_blacklisted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
