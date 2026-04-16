<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('downloaded_readings', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('status');
            $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_method');
            $table->decimal('amount_tendered', 10, 2)->nullable()->after('payment_amount');
            $table->decimal('change_amount', 10, 2)->nullable()->after('amount_tendered');
            $table->string('payment_reference')->nullable()->after('change_amount');
            $table->text('payment_remarks')->nullable()->after('payment_reference');
            $table->timestamp('paid_at')->nullable()->after('payment_remarks');
        });

        DB::statement("
            ALTER TABLE downloaded_readings
            MODIFY COLUMN status ENUM('pending', 'completed', 'paid') DEFAULT 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE downloaded_readings
            MODIFY COLUMN status ENUM('pending', 'completed') DEFAULT 'pending'
        ");

        Schema::table('downloaded_readings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_amount',
                'amount_tendered',
                'change_amount',
                'payment_reference',
                'payment_remarks',
                'paid_at',
            ]);
        });
    }
};

