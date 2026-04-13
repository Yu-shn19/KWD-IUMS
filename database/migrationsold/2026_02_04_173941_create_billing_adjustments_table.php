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
        if (!Schema::hasTable('billing_adjustments')) {
            Schema::create('billing_adjustments', function (Blueprint $table) {
                $table->id();
                $table->string('type', 10)->default('CM'); // CM or DM
                $table->string('type_ar', 10)->default('AR'); // AR or LRO
                $table->date('date');
                $table->string('bam_no', 50)->unique()->index(); // Auto-generated BAM number
                $table->string('account_no', 50)->index();
                // Use same type as consumer_ledgers uses for consumer_zone_id
                // Check existing consumer_ledgers table structure
                $table->unsignedBigInteger('consumer_zone_id')->nullable()->index();
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('acct_code', 50)->nullable();
                $table->string('reference', 100)->nullable();
                $table->decimal('current_bill', 10, 2)->default(0);
                $table->decimal('penalty', 10, 2)->default(0);
                $table->decimal('arrears', 10, 2)->default(0);
                $table->decimal('sc_discount', 10, 2)->default(0);
                $table->decimal('loans', 10, 2)->default(0);
                $table->decimal('others', 10, 2)->default(0);
                $table->text('remarks')->nullable();
                $table->string('status', 20)->default('Pending'); // Pending, Approved, Cancelled
                $table->integer('connect_reading')->default(0);
                $table->string('username')->nullable();
                $table->timestamps();
            });
        }

        // Note: Foreign key constraint removed due to type mismatch issues
        // The relationship is maintained at the application level through the model
        // If needed, add foreign key manually after verifying column types match exactly
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_adjustments');
    }
};
