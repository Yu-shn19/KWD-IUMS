<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * LRO Ledger: Billing Adjustment Memo entries (Type CM/DM, AR/LRO, etc.)
     */
    public function up(): void
    {
        Schema::create('lro_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('CM'); // CM, DM, <auto>
            $table->date('date')->nullable();
            $table->string('account', 50)->nullable()->index();
            $table->string('name')->nullable();
            $table->string('bam_no', 50)->nullable(); // BAM No.
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('ar_type', 10)->default('AR'); // AR, LRO (Type for AR/LRO)
            $table->string('acct_code', 50)->nullable();
            $table->string('reference', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('Pending'); // Pending, Approved, Cancelled
            $table->decimal('correct_reading', 12, 2)->default(0)->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lro_ledger');
    }
};
