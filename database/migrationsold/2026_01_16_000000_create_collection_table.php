<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create collection table only if it does not exist
        if (!Schema::hasTable('collection')) {
            Schema::create('collection', function (Blueprint $table) {
            $table->id();
            $table->string('zone_code')->nullable()->index();
            $table->unsignedInteger('sequence')->nullable();
            $table->string('account_no')->index();
            $table->string('account_name')->nullable();
            $table->date('coll_date')->nullable();
            $table->time('coll_time')->nullable();
            $table->string('pay_mode')->nullable();
            $table->string('or_number')->nullable()->index();
            $table->decimal('pay_amount', 12, 2)->default(0);
            $table->decimal('current_bill', 12, 2)->default(0);
            $table->decimal('meter_rental', 12, 2)->default(0);
            $table->decimal('arrears', 12, 2)->default(0);
            $table->decimal('penalty', 12, 2)->default(0);
            $table->decimal('materials', 12, 2)->default(0);
            $table->decimal('others', 12, 2)->default(0);
            $table->decimal('advances', 12, 2)->default(0);
            $table->decimal('sc_discount', 12, 2)->default(0);
            $table->decimal('fees_charges', 12, 2)->default(0);
            $table->decimal('materials_loan', 12, 2)->default(0);
            $table->decimal('prev_yr', 12, 2)->default(0);
            $table->string('cancel')->nullable();
            $table->string('username')->nullable();
            $table->decimal('sund1_amt', 12, 2)->default(0);
            $table->decimal('sund2_amt', 12, 2)->default(0);
            $table->decimal('sund3_amt', 12, 2)->default(0);
            $table->decimal('sund4_amt', 12, 2)->default(0);
            $table->decimal('sund5_amt', 12, 2)->default(0);
            $table->string('sund1_code')->nullable();
            $table->string('sund2_code')->nullable();
            $table->string('sund3_code')->nullable();
            $table->string('sund4_code')->nullable();
            $table->string('sund5_code')->nullable();
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('account_no');
            $table->index('coll_date');
            $table->index(['zone_code', 'coll_date']);
            });
        }
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection');
    }
};
