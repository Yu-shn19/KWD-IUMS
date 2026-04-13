<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table for reader app "Retrieve Zone" feature.
 * Stores downloaded readings per reader, zone, and reading_date.
 * Run this migration if the table does not exist yet.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('downloaded_readings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reader_id')->index();
            $table->string('zone', 64)->nullable()->index();
            $table->date('reading_date')->index();
            $table->string('account_number', 64)->nullable();
            $table->string('account_no', 64)->nullable();
            $table->string('account_name')->nullable();
            $table->string('name')->nullable();
            $table->decimal('current_reading', 12, 2)->nullable();
            $table->decimal('reading', 12, 2)->nullable();
            $table->decimal('consumption', 12, 2)->nullable();
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('downloaded_readings');
    }
};
