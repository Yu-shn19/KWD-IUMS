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
                $table->foreignId('consumer_zone_id')
                ->constrained('consumer_zone')
                ->cascadeOnDelete();
                $table->string('type', 10)->default('CM'); // CM or DM
                $table->string('ledger', 10)->default('AR'); // AR or LRO
                $table->date('date');
                $table->string('bam_no', 50)->unique()->index(); // Auto-generated BAM number
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('acct_code', 50)->nullable();
                $table->text('remarks')->nullable();
                $table->string('status', 20)->default('Pending'); // Pending, Approved, Cancelled
                $table->integer('connect_reading')->default(0);
                $table->string('username')->nullable();
                $table->timestamps();
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_adjustments');
    }
};
