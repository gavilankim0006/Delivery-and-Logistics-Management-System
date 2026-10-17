<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/GoogleMapsService.php';

/**
 * Route & Geocoding API Controller
 * Provides routing calculations, fee estimation, and Places autocomplete
 */
class Route_api extends Controller
{
    protected GoogleMapsService $mapsService;

    public function __construct()
    {
        parent::__construct();
        $this->call->helper('fee');
        $this->mapsService = new GoogleMapsService();
    }

    /**
     * POST /api/route/calculate
     * Calculates distance, duration, overview polyline, and estimated delivery fee
     */
    public function calculate()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $origin = $input['origin'] ?? $input['pickup_address'] ?? null;
        $destination = $input['destination'] ?? $input['dropoff_address'] ?? null;
        $weightKg = (float)($input['weight_kg'] ?? 1.0);

        // Accept coordinates if passed
        if (empty($origin) && isset($input['pickup_lat'], $input['pickup_lng'])) {
            $origin = ['lat' => (float)$input['pickup_lat'], 'lng' => (float)$input['pickup_lng']];
        }
        if (empty($destination) && isset($input['dropoff_lat'], $input['dropoff_lng'])) {
            $destination = ['lat' => (float)$input['dropoff_lat'], 'lng' => (float)$input['dropoff_lng']];
        }

        if (empty($origin)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Pickup address is required.']);
            exit;
        }

        if (empty($destination)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Drop-off address is required.']);
            exit;
        }

        try {
            $route = $this->mapsService->route($origin, $destination);
            $feeBreakdown = calculate_delivery_fee($route['distance_km'], $weightKg);

            echo json_encode([
                'success' => true,
                'data' => [
                    'distance_km' => $route['distance_km'],
                    'duration_min' => $route['duration_min'],
                    'fee' => $feeBreakdown['total_fee'],
                    'fee_breakdown' => $feeBreakdown,
                    'start_address' => $route['start_address'],
                    'end_address' => $route['end_address'],
                    'start_location' => $route['start_location'],
                    'end_location' => $route['end_location'],
                    'overview_polyline' => $route['overview_polyline'],
                    'source' => $route['source'] ?? 'google_api'
                ]
            ]);
            exit;
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Unable to calculate delivery route. Please verify addresses.']);
            exit;
        }
    }

    /**
     * GET /api/places/autocomplete?q=...
     * Autocomplete suggestions for Philippine addresses
     */
    public function autocomplete()
    {
        header('Content-Type: application/json');

        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        try {
            $suggestions = $this->mapsService->place_autocomplete($query);
            echo json_encode(['success' => true, 'data' => $suggestions]);
            exit;
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Autocomplete lookup failed.']);
            exit;
        }
    }
}
