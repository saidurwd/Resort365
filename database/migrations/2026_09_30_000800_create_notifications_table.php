<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app (database channel) notifications, with tenant_id (App\Models\DatabaseNotification).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'notifiable_type', 'notifiable_id', 'read_at'], 'notifications_tenant_notifiable_read_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
