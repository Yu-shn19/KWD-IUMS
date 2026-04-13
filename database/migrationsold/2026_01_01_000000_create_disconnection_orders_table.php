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
        // Skip creating the table if it already exists to avoid migration errors
        if (!Schema::hasTable('disconnection_orders')) {
            Schema::create('disconnection_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consumer_id');
                $table->unsignedBigInteger('disconnector_id')->nullable();
                $table->string('account_no')->index();
                $table->string('account_name');
                $table->string('address')->nullable();
                $table->string('zone_code')->nullable();
                $table->string('meter_number')->nullable();
                $table->integer('card_number')->nullable();
                $table->decimal('this_month_arrears', 10, 2)->default(0);
                $table->decimal('last_month_arrears', 10, 2)->default(0);
                $table->decimal('others_ar', 10, 2)->default(0);
                $table->decimal('total_outstanding', 10, 2);
                $table->integer('unpaid_months');
                $table->date('oldest_unpaid_date');
                $table->date('latest_unpaid_date');
                $table->date('disconnection_date');
                $table->enum('status', ['pending', 'assigned', 'in-progress', 'disconnected', 'reconnected', 'cancelled'])->default('pending');
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('disconnected_at')->nullable();
                $table->timestamp('reconnected_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('consumer_id')
                    ->references('id')
                    ->on('consumer_zone')
                    ->onDelete('cascade');
                
                $table->foreign('disconnector_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disconnection_orders');
    }
};
