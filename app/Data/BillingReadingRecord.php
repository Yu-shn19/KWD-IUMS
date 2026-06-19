<?php

namespace App\Data;

/**
 * Normalized billing lookup reading payload (replaces anonymous stdClass objects).
 */
class BillingReadingRecord
{
    public ?int $downloaded_id = null;

    public ?int $schedule_id = null;

    public ?int $reader_id = null;

    public ?string $account_number = null;

    public ?string $account_name = null;

    public ?string $zone = null;

    public float|int|null $previous_reading = 0;

    public float|int|null $current_reading = null;

    public float|int|null $consumption = 0;

    public ?float $downloaded_current_bill = null;

    public ?string $reading_date = null;

    public string $status = 'Prepared';

    public ?string $reader_notes = null;

    public ?string $completed_at = null;

    public ?string $payment_method = null;

    public ?float $payment_amount = null;

    public ?float $amount_tendered = null;

    public ?float $change_amount = null;

    public ?string $official_receipt_number = null;

    public ?string $payment_remarks = null;

    public ?string $paid_at = null;

    public ?string $schedule_account_name = null;

    public ?string $address = null;

    public ?string $category = null;

    public ?string $meter_number = null;

    public ?string $bill_month = null;

    public ?string $bill_date = null;

    public ?string $due_date = null;

    public ?string $disconnection_date = null;

    public ?string $previous_reading_date = null;

    public ?float $schedule_current_bill = null;

    public ?float $arrears = null;

    public ?float $total_amount = null;

    public ?string $schedule_status = null;

    public ?string $sedr_number = null;

    public ?string $downloaded_created_at = null;

    public ?string $downloaded_updated_at = null;

    public ?string $payment_reference = null;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function make(array $attributes): self
    {
        $record = new self();

        foreach ($attributes as $key => $value) {
            if (property_exists($record, $key)) {
                $record->{$key} = $value;
            }
        }

        return $record;
    }

    public static function fromStdClass(object $row): self
    {
        return self::make(get_object_vars($row));
    }
}
