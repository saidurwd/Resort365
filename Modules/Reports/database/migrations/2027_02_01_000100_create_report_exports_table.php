<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Report exports (Step 7.1, ARCHITECTURE §5.17): a report asked for as an Excel or PDF file is made in the background; this row is
 * the request, and then the file. The filters are kept as the report was asked for (properties and dates, never "current").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('report_key', 60);
            $table->string('title', 200);
            $table->string('format', 10);
            $table->json('filters');
            $table->string('status', 10);
            $table->string('path', 300)->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('rows_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'created_at']);
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
