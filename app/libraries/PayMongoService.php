<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class PayMongoService
{
    private string $secretKey;
    private string $baseUrl;
    private $transport;

    public function __construct(?string $secretKey = null, ?string $baseUrl = null, ?callable $transport = null)
    {
        $defaultSecret = function_exists('config_item') ? (config_item('paymongo_secret_key') ?: (getenv('PAYMONGO_SECRET_KEY') ?: '')) : (getenv('PAYMONGO_SECRET_KEY') ?: '');
        $defaultUrl = function_exists('config_item') ? (config_item('paymongo_base_url') ?: (getenv('PAYMONGO_BASE_URL') ?: 'https://api.paymongo.com/v1')) : (getenv('PAYMONGO_BASE_URL') ?: 'https://api.paymongo.com/v1');
        $this->secretKey = trim($secretKey ?? $defaultSecret);
        $this->baseUrl = rtrim($baseUrl ?? $defaultUrl, '/');
        $this->transport = $transport;
        if ($this->secretKey !== '' && !preg_match('/^sk_test_[A-Za-z0-9]+$/', $this->secretKey)) {
            throw new RuntimeException('PayMongo checkout requires a valid test secret key (sk_test_...).');
        }
    }

    public function createCheckoutSession(array $order, int $amountCentavos, string $successUrl, string $cancelUrl): array
    {
        if ($amountCentavos < 1) {
            throw new InvalidArgumentException('Checkout amount must be at least one centavo.');
        }
        $payload = ['data' => ['attributes' => [
            'line_items' => [[
                'currency' => 'PHP',
                'amount' => $amountCentavos,
                'name' => 'Delivery fee for ' . (string)$order['order_no'],
                'description' => 'Delivery service',
                'quantity' => 1
            ]],
            'payment_method_types' => ['card', 'gcash', 'grab_pay', 'paymaya'],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'description' => 'Delivery order ' . (string)$order['order_no'],
            'metadata' => ['order_id' => (string)(int)$order['id'], 'order_no' => (string)$order['order_no']]
        ]]];
        $result = $this->request('POST', '/checkout_sessions', $payload);
        $data = $result['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        if (empty($data['id']) || empty($attributes['checkout_url']) || !is_string($attributes['checkout_url'])) {
            throw new RuntimeException('PayMongo returned an incomplete checkout session.');
        }
        return ['id' => (string)$data['id'], 'checkout_url' => $attributes['checkout_url'], 'raw' => $data];
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,150}$/', $sessionId)) {
            throw new InvalidArgumentException('Invalid PayMongo checkout session ID.');
        }
        return $this->request('GET', '/checkout_sessions/' . rawurlencode($sessionId) . '?include[]=payments');
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (!preg_match('/^sk_test_[A-Za-z0-9]+$/', $this->secretKey)) {
            throw new RuntimeException('PayMongo requests require a valid test secret key (sk_test_...).');
        }
        $url = $this->baseUrl . $path;
        $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES);
        $headers = ['Authorization: Basic ' . base64_encode($this->secretKey . ':'), 'Content-Type: application/json', 'Accept: application/json'];
        if ($this->transport !== null) {
            $response = ($this->transport)($method, $url, $headers, $body);
        } else {
            $response = $this->curlRequest($method, $url, $headers, $body);
        }
        if (!is_array($response) || !isset($response['status'], $response['body'])) {
            throw new RuntimeException('PayMongo request returned an invalid transport response.');
        }
        $decoded = is_array($response['body']) ? $response['body'] : json_decode((string)$response['body'], true);
        if ((int)$response['status'] < 200 || (int)$response['status'] >= 300 || !is_array($decoded)) {
            $status = (int)$response['status'];
            $message = $decoded['errors'][0]['detail'] ?? 'PayMongo request failed.';
            throw new RuntimeException((string)$message, $status >= 100 && $status <= 599 ? $status : 0);
        }
        return $decoded;
    }

    private function curlRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $errno = curl_errno($curl);
        curl_close($curl);
        if ($responseBody === false) {
            throw new RuntimeException('PayMongo connection failed: ' . $error, $errno);
        }
        return ['status' => $status, 'body' => $responseBody];
    }
}
