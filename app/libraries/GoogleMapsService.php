<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/ApiClient.php';

/**
 * Google Maps Platform Service
 * Handles routing, distance/duration matrix calculations, and places integration.
 */
class GoogleMapsService extends ApiClient
{
    protected string $apiKey;
    protected bool $mockMode = false;

    // Known Metro Manila reference hubs for mock geocoding
    protected static array $knownHubs = [
        'makati'     => ['lat' => 14.5547, 'lng' => 121.0244, 'address' => 'Makati CBD, Ayala Avenue, Makati City'],
        'ayala'      => ['lat' => 14.5547, 'lng' => 121.0244, 'address' => 'Ayala Avenue, Makati City'],
        'bgc'        => ['lat' => 14.5176, 'lng' => 121.0509, 'address' => 'BGC High Street, 5th Avenue, Taguig City'],
        'taguig'     => ['lat' => 14.5176, 'lng' => 121.0509, 'address' => 'Bonifacio Global City, Taguig City'],
        'ortigas'    => ['lat' => 14.5869, 'lng' => 121.0614, 'address' => 'Ortigas Center, San Miguel Ave, Pasig City'],
        'pasig'      => ['lat' => 14.5869, 'lng' => 121.0614, 'address' => 'San Antonio, Pasig City'],
        'eastwood'   => ['lat' => 14.6105, 'lng' => 121.0805, 'address' => 'Eastwood City Cyberpark, Libis, Quezon City'],
        'greenhills' => ['lat' => 14.6025, 'lng' => 121.0503, 'address' => 'Greenhills Shopping Center, San Juan City'],
        'san juan'   => ['lat' => 14.6025, 'lng' => 121.0503, 'address' => 'Greenhills, San Juan City'],
        'alabang'    => ['lat' => 14.4253, 'lng' => 121.0315, 'address' => 'Alabang Town Center, Muntinlupa City'],
        'muntinlupa' => ['lat' => 14.4253, 'lng' => 121.0315, 'address' => 'Muntinlupa City, Metro Manila'],
        'manila'     => ['lat' => 14.5895, 'lng' => 120.9815, 'address' => 'Padre Burgos Ave, Ermita, Manila'],
        'naia'       => ['lat' => 14.5086, 'lng' => 121.0194, 'address' => 'Ninoy Aquino International Airport, Pasay City'],
        'pasay'      => ['lat' => 14.5378, 'lng' => 120.9993, 'address' => 'Mall of Asia Complex, Pasay City'],
        'quezon city'=> ['lat' => 14.6515, 'lng' => 121.0494, 'address' => 'Diliman, Quezon City, Metro Manila'],
        'cubao'      => ['lat' => 14.6200, 'lng' => 121.0530, 'address' => 'Araneta City, Cubao, Quezon City']
    ];

    public function __construct(?string $apiKey = null)
    {
        parent::__construct('google_maps', 'https://maps.googleapis.com/maps/api');
        $this->apiKey = $apiKey ?? (function_exists('config_item') ? (config_item('google_maps_api_key') ?: (getenv('GOOGLE_MAPS_API_KEY') ?: '')) : (getenv('GOOGLE_MAPS_API_KEY') ?: ''));
        $this->mockMode = empty($this->apiKey) || str_starts_with($this->apiKey, 'AIzaSyDemo');
    }

    /**
     * Compute route between origin and destination
     *
     * @param string|array $origin Address string or ['lat' => float, 'lng' => float]
     * @param string|array $destination Address string or ['lat' => float, 'lng' => float]
     * @param array $options Additional query parameters
     * @return array Route metrics: distance_km, duration_min, overview_polyline, start/end locations
     * @throws InvalidArgumentException
     */
    public function route($origin, $destination, array $options = []): array
    {
        $this->validateLocations($origin, $destination);

        $originStr = $this->formatLocationString($origin);
        $destStr = $this->formatLocationString($destination);

        if (!$this->mockMode) {
            try {
                $params = array_merge([
                    'origin' => $originStr,
                    'destination' => $destStr,
                    'key' => $this->apiKey,
                    'region' => 'ph',
                    'mode' => 'driving'
                ], $options);

                $response = $this->get('/directions/json', $params);

                if ($response['status'] === 200 && is_array($response['data'])) {
                    $apiStatus = $response['data']['status'] ?? '';

                    if ($apiStatus === 'OK' && !empty($response['data']['routes'])) {
                        $route = $response['data']['routes'][0];
                        $leg = $route['legs'][0];

                        return [
                            'distance_km' => round($leg['distance']['value'] / 1000, 2),
                            'duration_min' => (int) ceil($leg['duration']['value'] / 60),
                            'overview_polyline' => $route['overview_polyline']['points'] ?? '',
                            'start_address' => $leg['start_address'] ?? $originStr,
                            'end_address' => $leg['end_address'] ?? $destStr,
                            'start_location' => [
                                'lat' => (float)$leg['start_location']['lat'],
                                'lng' => (float)$leg['start_location']['lng']
                            ],
                            'end_location' => [
                                'lat' => (float)$leg['end_location']['lat'],
                                'lng' => (float)$leg['end_location']['lng']
                            ],
                            'source' => 'google_api'
                        ];
                    }

                    if (in_array($apiStatus, ['ZERO_RESULTS', 'NOT_FOUND'], true)) {
                        throw new \InvalidArgumentException("No route found between the specified locations. Please provide reachable addresses.");
                    }

                    // Key denied or billing error: fall through to fallback
                    $this->fallbackLog("Google Directions API returned status: {$apiStatus}");
                }
            } catch (\InvalidArgumentException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->fallbackLog("Google Directions request failed: " . $e->getMessage());
            }
        }

        // Mock / Offline computation
        return $this->computeMockRoute($origin, $destination);
    }

    /**
     * Autocomplete search for places restricted to the Philippines
     *
     * @param string $input User search query
     * @return array List of candidate suggestions
     */
    public function place_autocomplete(string $input): array
    {
        $query = trim($input);
        if (strlen($query) < 2) {
            return [];
        }

        if (!$this->mockMode) {
            try {
                $response = $this->get('/place/autocomplete/json', [
                    'input' => $query,
                    'components' => 'country:ph',
                    'key' => $this->apiKey
                ]);

                if ($response['status'] === 200 && ($response['data']['status'] ?? '') === 'OK') {
                    $predictions = [];
                    foreach ($response['data']['predictions'] as $p) {
                        $predictions[] = [
                            'description' => $p['description'],
                            'place_id' => $p['place_id']
                        ];
                    }
                    return $predictions;
                }
            } catch (\Throwable $e) {
                $this->fallbackLog("Places autocomplete failed: " . $e->getMessage());
            }
        }

        // Mock predictions
        $results = [];
        $lower = strtolower($query);
        foreach (self::$knownHubs as $key => $hub) {
            if (str_contains(strtolower($hub['address']), $lower) || str_contains($key, $lower)) {
                $results[] = [
                    'description' => $hub['address'],
                    'place_id' => 'mock_place_' . $key,
                    'lat' => $hub['lat'],
                    'lng' => $hub['lng']
                ];
            }
        }

        if (empty($results)) {
            $results[] = [
                'description' => "{$query}, Metro Manila, Philippines",
                'place_id' => 'mock_place_custom',
                'lat' => 14.5547,
                'lng' => 121.0244
            ];
        }

        return $results;
    }

    /**
     * Validate origin and destination inputs
     */
    protected function validateLocations($origin, $destination): void
    {
        if (empty($origin)) {
            throw new \InvalidArgumentException('Pickup address or coordinates are required.');
        }
        if (empty($destination)) {
            throw new \InvalidArgumentException('Drop-off address or coordinates are required.');
        }

        $originStr = $this->formatLocationString($origin);
        $destStr = $this->formatLocationString($destination);

        if (strcasecmp(trim($originStr), trim($destStr)) === 0) {
            throw new \InvalidArgumentException('Pickup and drop-off locations cannot be identical.');
        }

        // Validate coordinate bounds if coordinates are supplied
        if (is_array($origin) && isset($origin['lat'], $origin['lng'])) {
            $this->validateCoordinates((float)$origin['lat'], (float)$origin['lng'], 'Pickup');
        }
        if (is_array($destination) && isset($destination['lat'], $destination['lng'])) {
            $this->validateCoordinates((float)$destination['lat'], (float)$destination['lng'], 'Drop-off');
        }
    }

    /**
     * Validate latitude and longitude coordinate bounds
     */
    protected function validateCoordinates(float $lat, float $lng, string $label): void
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new \InvalidArgumentException("{$label} coordinates are out of valid geographic range.");
        }

        // Philippine bounding box: Lat 4.5°N - 21.5°N, Lng 116.0°E - 127.0°E
        if ($lat < 4.0 || $lat > 22.0 || $lng < 115.0 || $lng > 128.0) {
            throw new \InvalidArgumentException("{$label} location must be within the Philippines service area.");
        }
    }

    /**
     * Normalize location input into formatted query string
     */
    protected function formatLocationString($location): string
    {
        if (is_array($location)) {
            if (isset($location['lat'], $location['lng'])) {
                return "{$location['lat']},{$location['lng']}";
            }
            if (isset($location['address'])) {
                return trim($location['address']);
            }
            throw new \InvalidArgumentException('Location array must contain "lat" and "lng" or "address".');
        }

        $trimmed = trim((string)$location);
        if (empty($trimmed)) {
            throw new \InvalidArgumentException('Location cannot be blank.');
        }
        return $trimmed;
    }

    /**
     * Compute fallback realistic route using Haversine distance and known Metro Manila hubs
     */
    protected function computeMockRoute($origin, $destination): array
    {
        $originGeo = $this->resolveCoordinates($origin);
        $destGeo = $this->resolveCoordinates($destination);

        // Straight line distance
        $straightLineKm = $this->haversine($originGeo['lat'], $originGeo['lng'], $destGeo['lat'], $destGeo['lng']);

        // Road network factor in urban Metro Manila (~1.3x straight-line, min 1.5 km)
        $distanceKm = max(1.5, round($straightLineKm * 1.32, 2));

        // Speed in Metro Manila traffic averages ~20-25 km/h (~2.8 min per km) + 5 min base
        $durationMin = (int) max(10, ceil(($distanceKm * 2.8) + 5));

        // Mock polyline
        $polyline = $this->encodePolyline([
            [$originGeo['lat'], $originGeo['lng']],
            [($originGeo['lat'] + $destGeo['lat']) / 2 + 0.002, ($originGeo['lng'] + $destGeo['lng']) / 2 - 0.002],
            [$destGeo['lat'], $destGeo['lng']]
        ]);

        return [
            'distance_km' => $distanceKm,
            'duration_min' => $durationMin,
            'overview_polyline' => $polyline,
            'start_address' => $originGeo['address'],
            'end_address' => $destGeo['address'],
            'start_location' => [
                'lat' => $originGeo['lat'],
                'lng' => $originGeo['lng']
            ],
            'end_location' => [
                'lat' => $destGeo['lat'],
                'lng' => $destGeo['lng']
            ],
            'source' => 'mock_engine'
        ];
    }

    /**
     * Resolve latitude and longitude from string or array
     */
    protected function resolveCoordinates($location): array
    {
        if (is_array($location) && isset($location['lat'], $location['lng'])) {
            return [
                'lat' => (float)$location['lat'],
                'lng' => (float)$location['lng'],
                'address' => $location['address'] ?? "{$location['lat']}, {$location['lng']}"
            ];
        }

        $str = is_array($location) ? ($location['address'] ?? '') : (string)$location;
        $lower = strtolower($str);

        // Check if string is formatted "lat,lng"
        if (preg_match('/^([-+]?[0-9]*\.?[0-9]+)\s*,\s*([-+]?[0-9]*\.?[0-9]+)$/', $str, $matches)) {
            $lat = (float)$matches[1];
            $lng = (float)$matches[2];
            $this->validateCoordinates($lat, $lng, 'Location');
            return [
                'lat' => $lat,
                'lng' => $lng,
                'address' => "{$lat}, {$lng}"
            ];
        }

        // Match against known reference hubs
        foreach (self::$knownHubs as $key => $hub) {
            if (str_contains($lower, $key)) {
                return $hub;
            }
        }

        // Hash-based deterministic coordinate within Metro Manila
        $hash = crc32($str);
        $offsetLat = (($hash % 1000) / 10000.0) - 0.05;
        $offsetLng = ((($hash >> 8) % 1000) / 10000.0) - 0.05;

        return [
            'lat' => round(14.5547 + $offsetLat, 6),
            'lng' => round(121.0244 + $offsetLng, 6),
            'address' => $str
        ];
    }

    /**
     * Haversine formula to calculate great-circle distance between two points in km
     */
    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    /**
     * Encode array of [lat, lng] points into Google Maps Encoded Polyline format
     */
    protected function encodePolyline(array $points): string
    {
        $encoded = '';
        $prevLat = 0;
        $prevLng = 0;

        foreach ($points as $p) {
            $lat = (int)round($p[0] * 1e5);
            $lng = (int)round($p[1] * 1e5);

            $dLat = $lat - $prevLat;
            $dLng = $lng - $prevLng;

            $prevLat = $lat;
            $prevLng = $lng;

            $encoded .= $this->encodeSignedNumber($dLat);
            $encoded .= $this->encodeSignedNumber($dLng);
        }

        return $encoded;
    }

    protected function encodeSignedNumber(int $num): string
    {
        $sgnNum = $num < 0 ? ~($num << 1) : ($num << 1);
        return $this->encodeNumber($sgnNum);
    }

    protected function encodeNumber(int $num): string
    {
        $encode = '';
        while ($num >= 0x20) {
            $encode .= chr((0x20 | ($num & 0x1f)) + 63);
            $num >>= 5;
        }
        $encode .= chr($num + 63);
        return $encode;
    }
}
