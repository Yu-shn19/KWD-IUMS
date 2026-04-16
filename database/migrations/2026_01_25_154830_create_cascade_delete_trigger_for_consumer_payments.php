<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create trigger to cascade delete PAYMENT entries from consumer_ledgers
        // when a payment is deleted from consumer_payments (even via direct SQL)
        DB::unprepared('
            DROP TRIGGER IF EXISTS cascade_delete_payment_from_ledger;
            
            CREATE TRIGGER cascade_delete_payment_from_ledger
            AFTER DELETE ON consumer_payments
            FOR EACH ROW
            BEGIN
                -- Delete the corresponding PAYMENT entry in consumer_ledgers
                -- First try: Delete by direct link (consumer_payment_id) - most reliable
                -- Use COLLATE to ensure consistent collation for string comparisons
                DELETE FROM consumer_ledgers 
                WHERE consumer_payment_id = OLD.id 
                AND trans COLLATE utf8mb4_unicode_ci = "PAYMENT" COLLATE utf8mb4_unicode_ci;
                
                -- If no entry found by direct link (for older entries without consumer_payment_id),
                -- try matching by OR number, amount, and consumer_id
                IF OLD.or_number IS NOT NULL 
                   AND OLD.payment_amount IS NOT NULL 
                   AND OLD.consumer_id IS NOT NULL THEN
                    DELETE FROM consumer_ledgers 
                    WHERE consumer_zone_id = OLD.consumer_id 
                    AND trans COLLATE utf8mb4_unicode_ci = "PAYMENT" COLLATE utf8mb4_unicode_ci
                    AND reference COLLATE utf8mb4_unicode_ci = OLD.or_number COLLATE utf8mb4_unicode_ci
                    AND credit = OLD.payment_amount
                    AND (consumer_payment_id IS NULL OR consumer_payment_id != OLD.id)
                    LIMIT 1;
                END IF;
            END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS cascade_delete_payment_from_ledger;');
    }
};
