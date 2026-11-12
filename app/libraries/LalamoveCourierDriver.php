<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/ApiClient.php';
require_once APP_DIR . 'libraries/CourierProviderInterface.php';

class LalamoveCourierDriver extends ApiClient implements CourierProviderInterface
{
    private string $apiKey;
    private string $apiSecret;
    private string $market;

    public function __construct(?string $apiKey = null, ?string $apiSecret = null, ?string $baseUrl = null, ?string $market = null)
    {
        $this->apiKey = $apiKey ?? (getenv('LALAMOVE_API_KEY') ?: '');
        $this->apiSecret = $apiSecret ?? (getenv('LALAMOVE_API_SECRET') ?: '');
        $this->market = $market ?? (getenv('LALAMOVE_MARKET') ?: 'PH');
        if ($this->apiKey === '' || $this->apiSecret === '') {
            throw new RuntimeException('Lalamove driver selected but LALAMOVE_API_KEY and LALAMOVE_API_SECRET are not configured.');
        }
        parent::__construct('lalamove', $baseUrl ?? (getenv('LALAMOVE_BASE_URL') ?: 'https://rest.sandbox.lalamove.com'));
    }

    public function getQuote(array $order): array
    {
        if (!is_numeric($order['pickup_lat'] ?? null) || !is_numeric($order['pickup_lng'] ?? null)
            || !is_numeric($order['dropoff_lat'] ?? null) || !is_numeric($order['dropoff_lng'] ?? null)) {
            throw new InvalidArgumentException('Real Lalamove booking requires geocoded pickup and drop-off coordinates.');
        }
        // The optional `item` block is only accepted in TH/VN/HK and expects
        // market-specific weight/category enums, so it must not be sent for PH
        // (sending it triggers ERR_INVALID_FIELD / HTTP 422).
        $body = ['data' => [
            'serviceType' => 'MOTORCYCLE',
            'language' => 'en_PH',
            'stops' => [
                ['coordinates' => ['lat' => (string)($order['pickup_lat'] ?? '14.5547'), 'lng' => (string)($order['pickup_lng'] ?? '121.0244')], 'address' => (string)($order['pickup_address'] ?? '')],
                ['coordinates' => ['lat' => (string)($order['dropoff_lat'] ?? '14.5869'), 'lng' => (string)($order['dropoff_lng'] ?? '121.0614')], 'address' => (string)($order['dropoff_address'] ?? '')]
            ]
        ]];
        return $this->requestV3('POST', '/v3/quotations', $body);
    }

    public function placeOrder(array $order, array $quote): array
    {
        $data = $quote['data'] ?? [];
        $stops = $data['stops'] ?? [];
        if (empty($data['quotationId']) || count($stops) < 2) {
            throw new InvalidArgumentException('A valid Lalamove quotation with pickup and drop-off stops is required.');
        }
        $body = ['data' => [
            'quotationId' => $data['quotationId'],
            'sender' => [
                'stopId' => $stops[0]['stopId'],
                'name' => (string)($order['pickup_contact_name'] ?? 'Sender'),
                'phone' => $this->toE164((string)($order['pickup_phone'] ?? ''))
            ],
            'recipients' => [[
                'stopId' => $stops[1]['stopId'],
                'name' => (string)($order['dropoff_contact_name'] ?? 'Recipient'),
                'phone' => $this->toE164((string)($order['dropoff_phone'] ?? ''))
            ]]
        ]];
        return $this->requestV3('POST', '/v3/orders', $body);
    }

    public function cancelOrder(string $orderId, string $reason = ''): array
    {
        return $this->requestV3('DELETE', '/v3/orders/' . rawurlencode($orderId));
    }

    public function driverDetails(string $driverId, ?string $orderId = null): array
    {
        if ($orderId === null || $orderId === '') {
            throw new InvalidArgumentException('An order ID is required to retrieve Lalamove driver details.');
        }
        return $this->requestV3('GET', '/v3/orders/' . rawurlencode($orderId) . '/drivers/' . rawurlencode($driverId));
    }

    public function webhookEvent(string $eventType, array $order, array $extra = []): array
    {
        throw new LogicException('Synthetic webhook events are only available from the mock courier driver.');
    }

    private function requestV3(string $method, string $path, ?array $body = null): array
    {
        $bodyJson = $body === null ? '' : json_encode($body, JSON_UNESCAPED_SLASHES);
        $timestamp = (string)round(microtime(true) * 1000);
        $signature = hash_hmac('sha256', $timestamp . "\r\n" . strtoupper($method) . "\r\n" . $path . "\r\n\r\n" . $bodyJson, $this->apiSecret);
        $token = $this->apiKey . ':' . $timestamp . ':' . $signature;
        $headers = [
            'Authorization' => 'hmac ' . $token,
            'Market' => $this->market,
            'Request-ID' => bin2hex(random_bytes(16)),
            'Content-Type' => 'application/json'
        ];
        $response = $this->request($method, rtrim($this->baseUrl, '/') . $path, $bodyJson !== '' ? $bodyJson : null, $headers);
        if (empty($response['success']) || !is_array($response['data'])) {
            throw new RuntimeException('Lalamove API request failed: ' . $this->describeFailure($response));
        }
        return $response['data'];
    }

    /**
     * Build an actionable error message from a failed Lalamove response.
     *
     * Lalamove returns validation details (error id/message/detail and a
     * requestId) in the JSON body, so surface them instead of only "HTTP 422".
     *
     * @param array $response
     * @return string
     */
    private function describeFailure(array $response): string
    {
        $status = (int)($response['status'] ?? 0);
        $prefix = $status > 0 ? 'HTTP ' . $status : 'request error';
        $body = $response['data'] ?? null;

        if (!is_array($body)) {
            $fallback = (string)($response['error'] ?? 'invalid response');
            return $fallback !== '' ? $prefix . ' (' . $fallback . ')' : $prefix;
        }

        $parts = [];
        if (!empty($body['message']) && is_string($body['message'])) {
            $parts[] = $body['message'];
        }
        if (!empty($body['errors']) && is_array($body['errors'])) {
            foreach ($body['errors'] as $error) {
                if (!is_array($error)) {
                    continue;
                }
                $detail = trim(implode(' - ', array_filter([
                    (string)($error['id'] ?? ''),
                    (string)($error['message'] ?? ''),
                    (string)($error['detail'] ?? '')
                ], static fn($value) => $value !== '')));
                if ($detail !== '') {
                    $parts[] = $detail;
                }
            }
        }

        $message = $prefix;
        if (!empty($parts)) {
            $message .= ': ' . implode('; ', array_unique($parts));
        }
        $requestId = $body['meta']['requestId'] ?? null;
        if (is_string($requestId) && $requestId !== '') {
            $message .= ' (requestId: ' . $requestId . ')';
        }
        return $message;
    }

    /**
     * Normalize a phone number to the E.164 format required by Lalamove.
     *
     * The application stores Philippine numbers in local form (09XXXXXXXXX),
     * but Lalamove only accepts E.164 (e.g. +639XXXXXXXXX).
     *
     * @param string $phone
     * @return string
     */
    private function toE164(string $phone): string
    {
        $clean = preg_replace('/[\s\-\(\)\.]/', '', trim($phone));
        if ($clean === null || $clean === '') {
            return '';
        }
        if (preg_match('/^\+[1-9]\d{1,14}$/', $clean)) {
            return $clean;
        }
        if ($this->market === 'PH') {
            if (preg_match('/^0(\d{10})$/', $clean, $matches)) {   // 09XXXXXXXXX
                return '+63' . $matches[1];
            }
            if (preg_match('/^63(\d{10})$/', $clean, $matches)) {  // 639XXXXXXXXX
                return '+63' . $matches[1];
            }
            if (preg_match('/^9\d{9}$/', $clean)) {                // 9XXXXXXXXX
                return '+63' . $clean;
            }
        }
        return $clean;
    }
}
