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
        Schema::table('consumers', function (Blueprint $table) {
            $table->string('first_name')->after('full_name');
            $table->string('last_name')->after('first_name');
            $table->string('middle_name')->nullable()->after('last_name');
            $table->string('extension')->nullable()->after('middle_name');
            $table->string('contact_number')->after('extension');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'middle_name', 'extension', 'contact_number']);
        });
    }
};
