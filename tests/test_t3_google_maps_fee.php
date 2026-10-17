<?php
/**
 * Test Task T3: Google Maps Service & Dynamic Fee Calculation
 * Verifies:
 * 1. fee_helper calculation logic against admin settings rates
 * 2. GoogleMapsService::route() with valid addresses and coordinates
 * 3. GoogleMapsService input validation and error rejection
 * 4. HTTP POST /api/route/calculate endpoint response format and error handling
 * 5. HTTP GET /api/places/autocomplete endpoint for Philippine suggestions
 */

define('PREVENT_DIRECT_ACCESS', true);
define('APP_DIR', __DIR__ . '/../app/');
require_once __DIR__ . '/../app/helpers/fee_helper.php';
require_once __DIR__ . '/../app/libraries/GoogleMapsService.php';

$baseUrl = 'http://localhost/SIAproject';

function run_test(string $name, callable $fn): void {
    echo "Running test: {$name}... ";
    try {
        $fn();
        echo "[PASSED]\n";
    } catch (Throwable $e) {
        echo "[FAILED]\n  Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function http_post_json(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'body' => $raw,
        'json' => json_decode($raw, true) ?? []
    ];
}

// 1. Fee calculation logic with default admin settings
run_test("fee_helper calculates correct fees for standard and tiered distances/weights", function() {
    $rates = [
        'base_delivery_fee' => 60.00,
        'free_distance_km' => 2.0,
        'per_km_rate' => 12.50,
        'free_weight_kg' => 1.0,
        'per_kg_rate' => 5.00
    ];

    // Case A: 1.5 km (within 2km base), 1.0 kg (within 1kg base) -> 60.00
    $feeA = calculate_delivery_fee(1.5, 1.0, $rates);
    if ($feeA['total_fee'] !== 60.00 || $feeA['distance_fee'] !== 0.00 || $feeA['weight_fee'] !== 0.00) {
        throw new Exception("Case A expected 60.00, got " . json_encode($feeA));
    }

    // Case B: 7.4 km (5.4 km chargeable * 12.50 = 67.50), 1.0 kg -> 60 + 67.50 = 127.50
    $feeB = calculate_delivery_fee(7.4, 1.0, $rates);
    if ($feeB['total_fee'] !== 127.50 || $feeB['distance_fee'] !== 67.50) {
        throw new Exception("Case B expected 127.50, got " . json_encode($feeB));
    }

    // Case C: 7.4 km and 5.0 kg (4.0 kg chargeable * 5.00 = 20.00) -> 127.50 + 20 = 147.50
    $feeC = calculate_delivery_fee(7.4, 5.0, $rates);
    if ($feeC['total_fee'] !== 147.50 || $feeC['weight_fee'] !== 20.00) {
        throw new Exception("Case C expected 147.50, got " . json_encode($feeC));
    }

    // Case D: Invalid negative inputs rejected
    $rejected = false;
    try {
        calculate_delivery_fee(-5.0, 1.0, $rates);
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }
    if (!$rejected) {
        throw new Exception("Negative distance must throw InvalidArgumentException.");
    }
});

// 2. GoogleMapsService routing with valid Metro Manila locations
run_test("GoogleMapsService::route() returns distance, duration, and overview polyline for valid addresses", function() {
    $service = new GoogleMapsService();

    // Text landmark addresses in Metro Manila
    $route = $service->route('Makati CBD, Ayala Avenue', 'BGC High Street, Taguig City');
    if (!isset($route['distance_km']) || $route['distance_km'] <= 0) {
        throw new Exception("Expected positive distance_km, got: " . var_export($route['distance_km'] ?? null, true));
    }
    if (!isset($route['duration_min']) || $route['duration_min'] <= 0) {
        throw new Exception("Expected positive duration_min, got: " . var_export($route['duration_min'] ?? null, true));
    }
    if (empty($route['overview_polyline'])) {
        throw new Exception("Expected non-empty overview_polyline.");
    }
    if (!isset($route['start_location']['lat'], $route['start_location']['lng'])) {
        throw new Exception("Expected start_location coordinates.");
    }
    if (!isset($route['end_location']['lat'], $route['end_location']['lng'])) {
        throw new Exception("Expected end_location coordinates.");
    }
});

// 3. GoogleMapsService routing with coordinates
run_test("GoogleMapsService::route() accepts geographic coordinates within the Philippines", function() {
    $service = new GoogleMapsService();

    $origin = ['lat' => 14.5547, 'lng' => 121.0244]; // Makati
    $destination = ['lat' => 14.5869, 'lng' => 121.0614]; // Ortigas

    $route = $service->route($origin, $destination);
    if ($route['distance_km'] < 1.0) {
        throw new Exception("Expected distance >= 1.0 km between Makati and Ortigas, got {$route['distance_km']}");
    }
});

// 4. GoogleMapsService input validation error checks
run_test("GoogleMapsService rejects invalid inputs with clear error messages", function() {
    $service = new GoogleMapsService();

    // Check empty origin
    $emptyOriginRejected = false;
    try {
        $service->route('', 'BGC High Street');
    } catch (InvalidArgumentException $e) {
        if (strpos($e->getMessage(), 'Pickup') !== false) {
            $emptyOriginRejected = true;
        }
    }
    if (!$emptyOriginRejected) {
        throw new Exception("Empty pickup location should be rejected.");
    }

    // Check identical locations
    $identicalRejected = false;
    try {
        $service->route('Ayala Avenue, Makati', 'Ayala Avenue, Makati');
    } catch (InvalidArgumentException $e) {
        if (strpos($e->getMessage(), 'identical') !== false) {
            $identicalRejected = true;
        }
    }
    if (!$identicalRejected) {
        throw new Exception("Identical pickup and drop-off locations should be rejected.");
    }

    // Check foreign coordinates outside Philippines (e.g. London UK: 51.5074, -0.1278)
    $foreignRejected = false;
    try {
        $service->route(
            ['lat' => 51.5074, 'lng' => -0.1278],
            ['lat' => 14.5547, 'lng' => 121.0244]
        );
    } catch (InvalidArgumentException $e) {
        if (strpos($e->getMessage(), 'Philippines service area') !== false) {
            $foreignRejected = true;
        }
    }
    if (!$foreignRejected) {
        throw new Exception("Coordinates outside the Philippines service area should be rejected.");
    }
});

// 5. HTTP POST /api/route/calculate endpoint success & validation
run_test("HTTP POST /api/route/calculate computes route and returns JSON with fee breakdown", function() use ($baseUrl) {
    $res = http_post_json("{$baseUrl}/api/route/calculate", [
        'origin' => 'Ortigas Center, Pasig',
        'destination' => 'Eastwood City, Quezon City',
        'weight_kg' => 2.5
    ]);

    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got status {$res['status']}. Body: {$res['body']}");
    }
    if (empty($res['json']['success']) || !isset($res['json']['data'])) {
        throw new Exception("Invalid JSON response structure: {$res['body']}");
    }

    $data = $res['json']['data'];
    if ($data['distance_km'] <= 0 || $data['fee'] <= 0) {
        throw new Exception("Distance or fee not properly calculated: " . json_encode($data));
    }
    if (!isset($data['fee_breakdown']['base_fee'], $data['fee_breakdown']['total_fee'])) {
        throw new Exception("Fee breakdown missing from response.");
    }
});

// 6. HTTP POST /api/route/calculate error handling
run_test("HTTP POST /api/route/calculate returns HTTP 422 on invalid/missing address", function() use ($baseUrl) {
    $res = http_post_json("{$baseUrl}/api/route/calculate", [
        'origin' => '',
        'destination' => 'BGC Taguig'
    ]);

    if ($res['status'] !== 422) {
        throw new Exception("Expected HTTP 422 for missing pickup address, got: {$res['status']}");
    }
    if (empty($res['json']['error']) || strpos($res['json']['error'], 'Pickup') === false) {
        throw new Exception("Expected clear error message in JSON response: {$res['body']}");
    }
});

// 7. HTTP GET /api/places/autocomplete endpoint
run_test("HTTP GET /api/places/autocomplete returns suggestions for Philippine locations", function() use ($baseUrl) {
    $ch = curl_init("{$baseUrl}/api/places/autocomplete?q=Makati");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) {
        throw new Exception("Expected HTTP 200 for places autocomplete, got {$status}");
    }

    $json = json_decode($raw, true);
    if (empty($json['success']) || !is_array($json['data']) || count($json['data']) === 0) {
        throw new Exception("Expected non-empty candidate suggestions list: {$raw}");
    }
    if (strpos($json['data'][0]['description'], 'Makati') === false) {
        throw new Exception("First suggestion should contain 'Makati': " . json_encode($json['data'][0]));
    }
});

echo "\nAll Task T3 Google Maps & Fee Calculation acceptance tests PASSED!\n";
