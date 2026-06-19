<?php

namespace App\Services;

use App\Models\ConsumerZone;
use App\Models\DownloadedReading;

class DownloadedReadingPaymentContext
{
    public ?DownloadedReading $downloaded = null;

    public ?ConsumerZone $consumer = null;

    public ?int $consumerId = null;

    public ?float $outstandingBalance = null;   

    public ?string $accountNumber = null;
}
