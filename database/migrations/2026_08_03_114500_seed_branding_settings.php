<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Seed default branding keys into existing settings table (no new tables).
     */
    public function up(): void
    {
        $now = now();

        foreach (Setting::brandingDefaults() as $key => $value) {
            $exists = DB::table('settings')->where('key', $key)->exists();
            if ($exists) {
                continue;
            }

            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', Setting::brandingKeys())->delete();
    }
};
