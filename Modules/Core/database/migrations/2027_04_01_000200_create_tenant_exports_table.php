<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A company's export of all its data (Step 7.4): a ZIP of CSV files, one per table, and its attachments, kept for a week.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('requested_by_name', 120);
            $table->string('status', 10);
            $table->string('path', 255)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('tables_count')->nullable();
            $table->unsignedInteger('rows_count')->nullable();
            $table->unsignedInteger('files_count')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_exports');
    }
};
