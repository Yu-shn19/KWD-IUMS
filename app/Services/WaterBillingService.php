<?php

namespace App\Services;

class WaterBillingService
{
    public const METER_RENTAL = 20.00;

    private const MINIMUM_CHARGE = 253.00;

    /**
     * Water charge only (meter rental excluded — added separately in billing UI).
     */
    public function calculate(float $consumption, ?string $category = null, ?string $rateCode = null): float
    {
        $cu = max(0, (int) round($consumption));
        $classification = $this->resolveClassification($category, $rateCode);

        if ($this->usesResidentialMultiplier($classification)) {
            return round($this->computeResidentialBase($cu), 2);
        }

        return round($this->computeCommercialIndustrial($cu, $classification), 2);
    }

    /**
     * Full bill including meter rental (matches mobile ReadAndBill).
     */
    public function calculateWithMeterRental(float $consumption, ?string $category = null, ?string $rateCode = null): float
    {
        return round($this->calculate($consumption, $category, $rateCode) + self::METER_RENTAL, 2);
    }

    public function computeResidentialBase(int $cu): float
    {
        if ($cu <= 10) {
            return self::MINIMUM_CHARGE;
        }
        if ($cu <= 20) {
            return self::MINIMUM_CHARGE + (($cu - 10) * 27.00);
        }
        if ($cu <= 30) {
            return self::MINIMUM_CHARGE + (10 * 27.00) + (($cu - 20) * 28.75);
        }
        if ($cu <= 40) {
            return self::MINIMUM_CHARGE + (10 * 27.00) + (10 * 28.75) + (($cu - 30) * 30.55);
        }

        return self::MINIMUM_CHARGE
            + (10 * 27.00)
            + (10 * 28.75)
            + (10 * 30.55)
            + (($cu - 40) * 32.40);
    }

    /**
     * Commercial / Industrial (Category 32): residential base × classification multiplier.
     */
    public function computeCommercialIndustrial(int $cu, string $classification = 'INDUSTRIAL'): float
    {
        return $this->computeResidentialBase($cu) * $this->classificationMultiplier($classification);
    }

    public function classificationMultiplier(string $classification): float
    {
        $key = strtoupper(trim($classification));

        return match ($key) {
            'BULK', 'WHOLESALE' => 3.00,
            'INDUSTRIAL', 'COMMERCIAL' => 2.00,
            'COMMERCIAL A', 'COMMERCIAL_A', 'A' => 1.75,
            'COMMERCIAL B', 'COMMERCIAL_B', 'B' => 1.50,
            'COMMERCIAL C', 'COMMERCIAL_C', 'C' => 1.25,
            default => 1.00,
        };
    }

    public function resolveClassification(?string $category = null, ?string $rateCode = null): string
    {
        $raw = strtoupper(trim((string) ($category ?? '')));

        if ($raw !== '') {
            $mapped = match ($raw) {
                'COM-A' => 'COMMERCIAL A',
                'COM-B' => 'COMMERCIAL B',
                'COM-C' => 'COMMERCIAL C',
                'INDUSTRIAL' => 'INDUSTRIAL',
                'GOVT-LGU', 'GOVT', 'GOVERNMENT' => 'GOVERNMENT',
                'RES', 'RESIDENTIAL' => 'RESIDENTIAL',
                default => null,
            };

            if ($mapped !== null) {
                return $mapped;
            }

            if (is_numeric($raw)) {
                return match ((int) $raw) {
                    32 => 'INDUSTRIAL',
                    33 => 'COMMERCIAL A',
                    34 => 'COMMERCIAL B',
                    35 => 'COMMERCIAL C',
                    36 => 'WHOLESALE',
                    22 => 'GOVERNMENT',
                    default => 'RESIDENTIAL',
                };
            }

            if (str_contains($raw, 'IND')) {
                return 'INDUSTRIAL';
            }
            if (str_contains($raw, 'WHOLESALE') || str_contains($raw, 'BULK')) {
                return 'WHOLESALE';
            }
            if (str_contains($raw, 'COMA') || str_contains($raw, 'COMMERCIAL A')) {
                return 'COMMERCIAL A';
            }
            if (str_contains($raw, 'COMB') || str_contains($raw, 'COMMERCIAL B')) {
                return 'COMMERCIAL B';
            }
            if (str_contains($raw, 'COMC') || str_contains($raw, 'COMMERCIAL C')) {
                return 'COMMERCIAL C';
            }
            if (str_contains($raw, 'COM')) {
                return 'COMMERCIAL';
            }
            if (str_contains($raw, 'GOV')) {
                return 'GOVERNMENT';
            }
            if (str_contains($raw, 'RES')) {
                return 'RESIDENTIAL';
            }
        }

        $rate = strtoupper(trim((string) ($rateCode ?? '')));
        if ($rate !== '') {
            return $rate;
        }

        return 'RESIDENTIAL';
    }

    public function rateCodeFromCategory(?string $category): ?string
    {
        $raw = strtoupper(trim((string) ($category ?? '')));

        return match ($raw) {
            'COM-A' => 'A',
            'COM-B' => 'B',
            'COM-C' => 'C',
            'INDUSTRIAL' => 'INDUSTRIAL',
            'RES' => 'A',
            'GOVT', 'GOVT-LGU' => 'B',
            default => null,
        };
    }

    private function usesResidentialMultiplier(string $classification): bool
    {
        return in_array($classification, ['RESIDENTIAL', 'GOVERNMENT'], true);
    }
}
