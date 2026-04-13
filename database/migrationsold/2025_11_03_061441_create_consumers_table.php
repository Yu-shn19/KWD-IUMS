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
        Schema::create('consumers', function (Blueprint $table) {
            $table->id();
            $table->date('installation_date');
            $table->string('account_number')->unique();
            $table->string('meter_number');
            $table->text('address');
            $table->string('category');
            $table->string('status')->default('A - ACTIVE');
            $table->string('full_name');
            $table->string('meter_brand')->nullable();
            $table->string('zone');
            $table->string('card_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumers');
    }
};
