<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Fee Calculation Helper
 * Calculates dynamic delivery fees based on distance, parcel weight, and admin settings.
 */

if (!function_exists('get_delivery_rates')) {
    /**
     * Fetch active pricing rates from settings table with sensible fallbacks
     *
     * @return array
     */
    function get_delivery_rates(): array
    {
        static $cachedRates = null;
        if ($cachedRates !== null) {
            return $cachedRates;
        }

        $defaults = [
            'base_delivery_fee' => 60.00,
            'free_distance_km' => 2.0,
            'per_km_rate' => 12.50,
            'free_weight_kg' => 1.0,
            'per_kg_rate' => 5.00
        ];

        try {
            $db = lava_instance()->db ?? null;
            if ($db) {
                $rows = $db->table('settings')->get_all();
                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $key = $row['setting_key'];
                        if (array_key_exists($key, $defaults)) {
                            $defaults[$key] = (float)$row['setting_value'];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Use defaults on DB error
        }

        $cachedRates = $defaults;
        return $cachedRates;
    }
}

if (!function_exists('calculate_delivery_fee')) {
    /**
     * Calculate delivery fee for given distance and parcel weight
     *
     * @param float $distanceKm Delivery distance in kilometers
     * @param float $weightKg Parcel weight in kilograms (default 1.0)
     * @param array|null $customRates Optional custom pricing rates to override database settings
     * @return array Breakdown of calculated fees
     */
    function calculate_delivery_fee(float $distanceKm, float $weightKg = 1.0, ?array $customRates = null): array
    {
        if ($distanceKm < 0) {
            throw new \InvalidArgumentException('Distance cannot be negative.');
        }
        if ($weightKg < 0) {
            throw new \InvalidArgumentException('Weight cannot be negative.');
        }

        $rates = $customRates ?? get_delivery_rates();

        $baseFee = (float)($rates['base_delivery_fee'] ?? 60.00);
        $freeDistance = (float)($rates['free_distance_km'] ?? 2.0);
        $perKmRate = (float)($rates['per_km_rate'] ?? 12.50);
        $freeWeight = (float)($rates['free_weight_kg'] ?? 1.0);
        $perKgRate = (float)($rates['per_kg_rate'] ?? 5.00);

        // Distance surcharge
        $chargeableDistance = max(0.0, $distanceKm - $freeDistance);
        $distanceFee = round($chargeableDistance * $perKmRate, 2);

        // Weight surcharge
        $chargeableWeight = max(0.0, $weightKg - $freeWeight);
        $weightFee = round($chargeableWeight * $perKgRate, 2);

        // Total fee
        $totalFee = round($baseFee + $distanceFee + $weightFee, 2);

        return [
            'distance_km' => round($distanceKm, 2),
            'weight_kg' => round($weightKg, 2),
            'base_fee' => $baseFee,
            'chargeable_distance_km' => round($chargeableDistance, 2),
            'distance_fee' => $distanceFee,
            'chargeable_weight_kg' => round($chargeableWeight, 2),
            'weight_fee' => $weightFee,
            'total_fee' => $totalFee
        ];
    }
}
