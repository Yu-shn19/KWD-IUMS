<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create lro_ledgers table only if it does not exist
        if (!Schema::hasTable('lro_ledgers')) {
            Schema::create('lro_ledgers', function (Blueprint $table) {
                $table->id();
                $table->date('billmonth')->nullable()->index();
                $table->date('cba_date')->nullable()->index();
                $table->string('cba_type')->nullable();
                $table->string('cba_no')->nullable()->index();
                $table->string('zone_code')->nullable()->index();
                $table->string('account_no')->index();
                $table->string('account_name')->nullable();
                $table->text('cba_remarks')->nullable();
                $table->decimal('ar_dm', 12, 2)->default(0)->comment('Accounts Receivable Debit Memo');
                $table->decimal('ar_cm', 12, 2)->default(0)->comment('Accounts Receivable Credit Memo');
                $table->decimal('lro_dm', 12, 2)->default(0)->comment('LRO Debit Memo');
                $table->decimal('lro_cm', 12, 2)->default(0)->comment('LRO Credit Memo');
                $table->decimal('cba_amount', 12, 2)->default(0);
                $table->string('acct_group')->nullable();
                $table->integer('consumer_zone_id')->nullable()->index();
                $table->timestamps();
                
                // Indexes for better query performance
                $table->index('account_no');
                $table->index('account_name');
                $table->index('cba_date');
                $table->index(['zone_code', 'cba_date']);
                $table->index(['account_no', 'account_name']);
                
                // Foreign key to consumer_zone table (added separately to avoid constraint issues)
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lro_ledgers');
    }
};
