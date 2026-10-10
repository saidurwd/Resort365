<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash, bank and reconciliation (Step 4.4): bank and cash accounts (each tied to a ledger account), transfers
 * between them, imported bank statements, the matches between statement lines and ledger lines, completed
 * reconciliations, and the cheque columns of vouchers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('kind', 10)->default('bank');
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'account_id']);
        });

        Schema::create('fund_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('transfer_no', 30);
            $table->date('transfer_date');
            $table->foreignId('from_bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('to_bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reference', 100)->nullable();
            $table->string('notes', 300)->nullable();
            $table->string('status', 10)->default('posted');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 300)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'transfer_no']);
            $table->index(['tenant_id', 'property_id', 'transfer_date']);
        });

        Schema::create('bank_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->string('file_name', 190);
            $table->date('statement_from');
            $table->date('statement_to');
            $table->decimal('closing_balance', 15, 2);
            $table->unsignedInteger('line_count')->default(0);
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'bank_account_id', 'statement_to']);
        });

        Schema::create('bank_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_no');
            $table->date('txn_date');
            $table->string('description', 300);
            $table->string('reference', 100)->nullable();
            $table->decimal('withdrawal', 15, 2)->default(0);
            $table->decimal('deposit', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->nullable();
            $table->char('fingerprint', 40);
            $table->timestamps();

            $table->unique(['tenant_id', 'bank_account_id', 'fingerprint'], 'bank_statement_lines_fingerprint_unique');
            $table->index(['tenant_id', 'bank_statement_id']);
        });

        Schema::create('bank_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_statement_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->date('statement_date');
            $table->decimal('statement_balance', 15, 2);
            $table->decimal('book_balance', 15, 2);
            $table->decimal('outstanding_net', 15, 2);
            $table->decimal('difference', 15, 2);
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'bank_statement_id']);
        });

        Schema::create('bank_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_statement_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_reconciliation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('matched_by')->nullable();
            $table->timestamp('matched_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'bank_statement_line_id'], 'bank_matches_statement_line_unique');
            $table->unique(['tenant_id', 'journal_line_id'], 'bank_matches_journal_line_unique');
        });

        Schema::table('vouchers', function (Blueprint $table): void {
            $table->string('cheque_no', 30)->nullable()->after('reference');
            $table->date('cheque_date')->nullable()->after('cheque_no');
            $table->string('cheque_status', 10)->nullable()->after('cheque_date');
            $table->index(['tenant_id', 'cheque_status'], 'vouchers_cheque_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table): void {
            $table->dropIndex('vouchers_cheque_status_index');
            $table->dropColumn(['cheque_no', 'cheque_date', 'cheque_status']);
        });
        Schema::dropIfExists('bank_matches');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
        Schema::dropIfExists('fund_transfers');
        Schema::dropIfExists('bank_accounts');
    }
};
