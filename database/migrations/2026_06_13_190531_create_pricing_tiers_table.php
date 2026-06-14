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
        Schema::create('pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Residential", "Commercial A", "Rate Code C", etc.
            $table->string('category_id')->nullable(); // 12, 22, 32, 33, 34, 35, 36
            $table->string('rate_code')->nullable(); // C, D, or null
            $table->decimal('min_charge', 10, 2); // Minimum charge for 0-10 cubic meters
            $table->decimal('meter_rental', 10, 2)->default(20.00); // Meter rental charge
            
            // Tier 1: 11-20 cubic meters
            $table->decimal('tier1_rate', 10, 2)->nullable(); // Rate per cubic meter for tier 1
            $table->integer('tier1_max')->default(20); // Maximum cubic meters for tier 1
            
            // Tier 2: 21-30 cubic meters
            $table->decimal('tier2_rate', 10, 2)->nullable();
            $table->integer('tier2_max')->default(30);
            
            // Tier 3: 31-40 cubic meters
            $table->decimal('tier3_rate', 10, 2)->nullable();
            $table->integer('tier3_max')->default(40);
            
            // Tier 4: 41+ cubic meters
            $table->decimal('tier4_rate', 10, 2)->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['category_id', 'rate_code']);
            $table->index('is_active');
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_tiers');
    }
};
