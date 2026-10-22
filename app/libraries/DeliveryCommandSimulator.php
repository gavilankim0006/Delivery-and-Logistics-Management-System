<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'helpers/fee_helper.php';

class DeliveryCommandSimulator
{
    public static function normalizePolygon($coordinates): array
    {
        if (is_string($coordinates)) {
            $coordinates = json_decode($coordinates, true);
        }
        if (!is_array($coordinates) || count($coordinates) < 3 || count($coordinates) > 200) {
            throw new InvalidArgumentException('A service area needs between 3 and 200 polygon vertices.');
        }

        $ring = [];
        foreach ($coordinates as $point) {
            if (!is_array($point) || !isset($point['lat'], $point['lng']) || !is_numeric($point['lat']) || !is_numeric($point['lng'])) {
                throw new InvalidArgumentException('Every polygon vertex must include numeric latitude and longitude.');
            }
            $lat = (float)$point['lat'];
            $lng = (float)$point['lng'];
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw new InvalidArgumentException('A polygon vertex is outside valid coordinate bounds.');
            }
            $ring[] = ['lat' => round($lat, 7), 'lng' => round($lng, 7)];
        }

        if (self::samePoint($ring[0], $ring[count($ring) - 1])) {
            array_pop($ring);
        }
        if (count($ring) < 3 || count($ring) > 200) {
            throw new InvalidArgumentException('A service area needs between 3 and 200 unique vertices.');
        }
        $area = 0.0;
        $count = count($ring);
        for ($i = 0; $i < $count; $i++) {
            $next = ($i + 1) % $count;
            if (self::samePoint($ring[$i], $ring[$next])) {
                throw new InvalidArgumentException('A polygon cannot contain adjacent duplicate vertices.');
            }
            $area += ($ring[$i]['lng'] * $ring[$next]['lat']) - ($ring[$next]['lng'] * $ring[$i]['lat']);
        }
        if (abs($area) < 1e-10) {
            throw new InvalidArgumentException('Polygon vertices must enclose a non-zero area.');
        }
        for ($i = 0; $i < $count; $i++) {
            $iNext = ($i + 1) % $count;
            for ($j = $i + 1; $j < $count; $j++) {
                $jNext = ($j + 1) % $count;
                if ($i === $j || $iNext === $j || $jNext === $i) {
                    continue;
                }
                if (self::segmentsIntersect($ring[$i], $ring[$iNext], $ring[$j], $ring[$jNext])) {
                    throw new InvalidArgumentException('Polygon edges cannot cross or overlap.');
                }
            }
        }

        return $ring;
    }

    public static function pointInPolygon(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $count = count($ring);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $a = $ring[$i];
            $b = $ring[$j];
            if (self::pointOnSegment($lat, $lng, (float)$a['lat'], (float)$a['lng'], (float)$b['lat'], (float)$b['lng'])) {
                return true;
            }
            $crosses = (($a['lat'] > $lat) !== ($b['lat'] > $lat))
                && ($lng < (($b['lng'] - $a['lng']) * ($lat - $a['lat']) / (($b['lat'] - $a['lat']) ?: 1e-12)) + $a['lng']);
            if ($crosses) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

    public static function simulate(array $input, array $baselineRates, int $availableRiders): array
    {
        $volume = self::boundedNumber($input['projected_orders'] ?? null, 'Projected orders', 1, 5000, true);
        $distance = self::boundedNumber($input['average_distance_km'] ?? null, 'Average distance', 0.1, 1000);
        $weight = self::boundedNumber($input['average_weight_kg'] ?? null, 'Average parcel weight', 0.1, 1000);
        $hours = self::boundedNumber($input['scenario_hours'] ?? null, 'Scenario duration', 1, 24);
        $perRider = self::boundedNumber($input['deliveries_per_rider_hour'] ?? null, 'Rider throughput', 0.1, 20);
        $riderCount = self::boundedNumber($input['riders'] ?? $availableRiders, 'Rider count', 0, 500, true);

        $proposedRates = [];
        foreach (['base_delivery_fee', 'free_distance_km', 'per_km_rate', 'free_weight_kg', 'per_kg_rate'] as $key) {
            $value = $input[$key] ?? ($baselineRates[$key] ?? null);
            $proposedRates[$key] = self::boundedNumber($value, 'Fee rate', 0, 100000);
        }

        $baselineFee = calculate_delivery_fee($distance, $weight, $baselineRates)['total_fee'];
        $scenarioFee = calculate_delivery_fee($distance, $weight, $proposedRates)['total_fee'];
        $capacity = (int)floor($riderCount * $perRider * $hours);
        $workloadHours = $riderCount > 0 ? round($volume / ($riderCount * $perRider), 1) : null;

        return [
            'projected_orders' => (int)$volume,
            'available_riders' => (int)$riderCount,
            'scenario_hours' => $hours,
            'capacity' => $capacity,
            'projected_backlog' => max(0, (int)$volume - $capacity),
            'estimated_workload_hours' => $workloadHours,
            'baseline_fee' => $baselineFee,
            'scenario_fee' => $scenarioFee,
            'baseline_gross_revenue' => round($baselineFee * $volume, 2),
            'scenario_gross_revenue' => round($scenarioFee * $volume, 2),
            'proposed_rates' => $proposedRates
        ];
    }

    private static function boundedNumber($value, string $label, float $min, float $max, bool $integer = false): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException($label . ' must be numeric.');
        }
        $number = (float)$value;
        if (!is_finite($number) || $number < $min || $number > $max || ($integer && floor($number) !== $number)) {
            throw new InvalidArgumentException($label . ' is outside the allowed range.');
        }
        return $number;
    }

    private static function samePoint(array $a, array $b): bool
    {
        return abs((float)$a['lat'] - (float)$b['lat']) < 1e-7 && abs((float)$a['lng'] - (float)$b['lng']) < 1e-7;
    }

    private static function segmentsIntersect(array $a, array $b, array $c, array $d): bool
    {
        $orient = static function (array $p, array $q, array $r): float {
            return ((float)$q['lng'] - (float)$p['lng']) * ((float)$r['lat'] - (float)$p['lat'])
                - ((float)$q['lat'] - (float)$p['lat']) * ((float)$r['lng'] - (float)$p['lng']);
        };
        $o1 = $orient($a, $b, $c);
        $o2 = $orient($a, $b, $d);
        $o3 = $orient($c, $d, $a);
        $o4 = $orient($c, $d, $b);
        $opposite = static fn(float $x, float $y): bool => ($x > 1e-10 && $y < -1e-10) || ($x < -1e-10 && $y > 1e-10);
        if ($opposite($o1, $o2) && $opposite($o3, $o4)) {
            return true;
        }
        $onSegment = static function (array $p, array $q, array $r): bool {
            return (float)$q['lng'] >= min((float)$p['lng'], (float)$r['lng']) - 1e-10
                && (float)$q['lng'] <= max((float)$p['lng'], (float)$r['lng']) + 1e-10
                && (float)$q['lat'] >= min((float)$p['lat'], (float)$r['lat']) - 1e-10
                && (float)$q['lat'] <= max((float)$p['lat'], (float)$r['lat']) + 1e-10;
        };
        return (abs($o1) <= 1e-10 && $onSegment($a, $c, $b))
            || (abs($o2) <= 1e-10 && $onSegment($a, $d, $b))
            || (abs($o3) <= 1e-10 && $onSegment($c, $a, $d))
            || (abs($o4) <= 1e-10 && $onSegment($c, $b, $d));
    }

    private static function pointOnSegment(float $lat, float $lng, float $lat1, float $lng1, float $lat2, float $lng2): bool
    {
        $cross = ($lng - $lng1) * ($lat2 - $lat1) - ($lat - $lat1) * ($lng2 - $lng1);
        if (abs($cross) > 1e-8) {
            return false;
        }
        return $lng >= min($lng1, $lng2) - 1e-8 && $lng <= max($lng1, $lng2) + 1e-8
            && $lat >= min($lat1, $lat2) - 1e-8 && $lat <= max($lat1, $lat2) + 1e-8;
    }
}
