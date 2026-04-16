<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add stable consumer link to legacy lro_ledger rows.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lro_ledger')) {
            return;
        }

        if (!Schema::hasColumn('lro_ledger', 'consumer_zone_id')) {
            Schema::table('lro_ledger', function (Blueprint $table) {
                $table->unsignedBigInteger('consumer_zone_id')->nullable()->after('name')->index();
            });
        }

        DB::table('lro_ledger')
            ->whereNull('consumer_zone_id')
            ->whereNotNull('account')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $account = trim((string) ($row->account ?? ''));
                    if ($account === '') {
                        continue;
                    }

                    $consumerId = DB::table('consumer_zone')
                        ->where('account_no', $account)
                        ->value('id');

                    if (!$consumerId) {
                        $normalized = str_replace('-', '', $account);
                        $consumerId = DB::table('consumer_zone')
                            ->whereRaw("REPLACE(TRIM(account_no), '-', '') = ?", [$normalized])
                            ->value('id');
                    }

                    if ($consumerId) {
                        DB::table('lro_ledger')
                            ->where('id', $row->id)
                            ->update(['consumer_zone_id' => $consumerId]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('lro_ledger') && Schema::hasColumn('lro_ledger', 'consumer_zone_id')) {
            Schema::table('lro_ledger', function (Blueprint $table) {
                $table->dropColumn('consumer_zone_id');
            });
        }
    }
};

