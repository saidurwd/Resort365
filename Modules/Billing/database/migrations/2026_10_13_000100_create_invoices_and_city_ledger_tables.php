<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Check-out documents (ARCHITECTURE §5.9, §8.3): invoices (immutable, with their lines and tax
 * breakdown), credit notes, and the city ledger (company accounts receivable). Folio lines gain
 * their tax breakdown; payments gain refund and city-ledger references.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folio_lines', function (Blueprint $table): void {
            $table->json('tax_lines')->nullable()->after('tax_amount');
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('folio_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('invoice_no', 30);
            $table->date('issue_date');
            $table->string('bill_to_type', 20);
            $table->unsignedBigInteger('bill_to_id')->nullable();
            $table->string('bill_to_name', 150);
            $table->string('bill_to_tax_number', 50)->nullable();
            $table->char('currency_code', 3);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_total', 15, 2);
            $table->decimal('total', 15, 2);
            $table->decimal('paid', 15, 2)->default(0);
            $table->decimal('on_account', 15, 2)->default(0);
            $table->decimal('credited', 15, 2)->default(0);
            $table->json('tax_breakdown')->nullable();
            $table->string('status', 20)->default('issued');
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_no']);
            $table->index(['tenant_id', 'property_id', 'issue_date']);
            $table->index(['tenant_id', 'reservation_id']);
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_line_id')->nullable()->constrained()->nullOnDelete();
            $table->date('service_date');
            $table->string('charge_code', 20)->nullable();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
        });

        Schema::create('city_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->date('posted_on');
            $table->date('due_on');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->decimal('paid', 15, 2)->default(0);
            $table->decimal('credited', 15, 2)->default(0);
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->index(['tenant_id', 'company_id', 'status']);
        });

        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('credit_note_no', 30);
            $table->date('issue_date');
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->decimal('applied_to_ledger', 15, 2)->default(0);
            $table->decimal('refund_due', 15, 2)->default(0);
            $table->decimal('refunded', 15, 2)->default(0);
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'credit_note_no']);
            $table->index(['tenant_id', 'invoice_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('reason')->nullable()->after('notes');
            $table->string('refund_kind', 30)->nullable()->after('reason');
            $table->foreignId('refunded_payment_id')->nullable()->after('refund_kind')->constrained('payments')->nullOnDelete();
            $table->foreignId('credit_note_id')->nullable()->after('refunded_payment_id')->constrained()->nullOnDelete();
            $table->foreignId('city_ledger_entry_id')->nullable()->after('credit_note_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['refunded_payment_id']);
            $table->dropForeign(['credit_note_id']);
            $table->dropForeign(['city_ledger_entry_id']);
            $table->dropColumn(['reason', 'refund_kind', 'refunded_payment_id', 'credit_note_id', 'city_ledger_entry_id']);
        });
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('city_ledger_entries');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::table('folio_lines', function (Blueprint $table): void {
            $table->dropColumn('tax_lines');
        });
    }
};
