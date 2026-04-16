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
        // Only create the table if it doesn't exist
        if (!Schema::hasTable('consumer_ledgers')) {
            Schema::create('consumer_ledgers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consumer_zone_id')->nullable()->index();
                $table->unsignedBigInteger('consumer_payment_id')->nullable()->index();
                $table->unsignedBigInteger('schedule_id')->nullable()->index();
                $table->unsignedBigInteger('downloaded_reading_id')->nullable()->index();
                $table->string('trans')->nullable()->index();
                $table->string('date')->nullable()->index();
                $table->date('due_date')->nullable()->index();
                $table->string('reference')->nullable();
                $table->decimal('reading', 10, 2)->default(0);
                $table->decimal('volume', 10, 2)->default(0);
                $table->decimal('billamount', 10, 2)->default(0);
                $table->decimal('penalty', 10, 2)->default(0);
                $table->decimal('others', 10, 2)->default(0);
                $table->decimal('debit', 10, 2)->default(0)->index();
                $table->decimal('credit', 10, 2)->default(0)->index();
                $table->decimal('balance', 10, 2)->default(0)->index();
                $table->string('username')->nullable();
                $table->timestamp('txtime')->nullable()->index();
                $table->string('cl_ctrl')->nullable();
                $table->timestamps();

                // Foreign keys
                $table->foreign('consumer_zone_id')
                    ->references('id')->on('consumer_zone')
                    ->onDelete('cascade');

                $table->foreign('consumer_payment_id')
                    ->references('id')->on('consumer_payments')
                    ->onDelete('cascade');

                $table->foreign('schedule_id')
                    ->references('id')->on('meter_reading_schedules')
                    ->onDelete('cascade');

                $table->foreign('downloaded_reading_id')
                    ->references('id')->on('downloaded_readings')
                    ->onDelete('cascade');

                // Composite indexes for performance
                $table->index(['consumer_zone_id', 'date']);
                $table->index(['consumer_zone_id', 'trans']);
                $table->index(['consumer_zone_id', 'due_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumer_ledgers');
    }
};
