<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

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
        $this->mapsService = $this->call->library('GoogleMapsService');
        $this->call->library('api');
    }

    /**
     * POST /api/route/calculate
     * Calculates distance, duration, overview polyline, and estimated delivery fee
     */
    public function calculate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->api->respond(['success' => false, 'error' => 'Method Not Allowed'], 405);
        }

        $input = $this->api->body();
        if (empty($input)) {
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        }

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
            $this->api->respond(['success' => false, 'error' => 'Pickup address is required.'], 422);
        }

        if (empty($destination)) {
            $this->api->respond(['success' => false, 'error' => 'Drop-off address is required.'], 422);
        }

        try {
            $route = $this->mapsService->route($origin, $destination);
            $feeBreakdown = calculate_delivery_fee($route['distance_km'], $weightKg);

            $this->api->respond([
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
        } catch (\InvalidArgumentException $e) {
            $this->api->respond(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->api->respond(['success' => false, 'error' => 'Unable to calculate delivery route. Please verify addresses.'], 500);
        }
    }

    /**
     * GET /api/places/autocomplete?q=...
     * Autocomplete suggestions for Philippine addresses
     */
    public function autocomplete()
    {
        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            $this->api->respond(['success' => true, 'data' => []]);
        }

        try {
            $suggestions = $this->mapsService->place_autocomplete($query);
            $this->api->respond(['success' => true, 'data' => $suggestions]);
        } catch (\Throwable $e) {
            $this->api->respond(['success' => false, 'error' => 'Autocomplete lookup failed.'], 500);
        }
    }
}
