<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class SemaphoreService
{
    private string $apiKey;
    private string $baseUrl;
    private string $senderName;
    private $transport;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null, ?string $senderName = null, ?callable $transport = null)
    {
        $defaultKey = function_exists('config_item') ? (config_item('semaphore_api_key') ?: (getenv('SEMAPHORE_API_KEY') ?: '')) : (getenv('SEMAPHORE_API_KEY') ?: '');
        $defaultUrl = function_exists('config_item') ? (config_item('semaphore_base_url') ?: (getenv('SEMAPHORE_BASE_URL') ?: 'https://api.semaphore.co/api/v4')) : (getenv('SEMAPHORE_BASE_URL') ?: 'https://api.semaphore.co/api/v4');
        $defaultSender = function_exists('config_item') ? (config_item('semaphore_sender_name') ?: (getenv('SEMAPHORE_SENDER_NAME') ?: '')) : (getenv('SEMAPHORE_SENDER_NAME') ?: '');
        $this->apiKey = trim($apiKey ?? $defaultKey);
        $this->baseUrl = rtrim($baseUrl ?? $defaultUrl, '/');
        $this->senderName = trim($senderName ?? $defaultSender);
        $this->transport = $transport;
    }

    public function send(string $number, string $message): array
    {
        $enabled = function_exists('config_item') ? (bool)config_item('semaphore_enabled') : (getenv('SEMAPHORE_ENABLED') === '1');
        if (!$enabled && getenv('SEMAPHORE_ENABLED') !== '1') {
            return ['sent' => false, 'disabled' => true];
        }
        $cleanedNumber = preg_replace('/[^0-9]/', '', $number);
        if (str_starts_with($cleanedNumber, '639') && strlen($cleanedNumber) === 12) {
            $cleanedNumber = '09' . substr($cleanedNumber, 3);
        } elseif (str_starts_with($cleanedNumber, '9') && strlen($cleanedNumber) === 10) {
            $cleanedNumber = '0' . $cleanedNumber;
        }
        if ($this->apiKey === '' || !preg_match('/^09\d{9}$/', $cleanedNumber)) {
            $reason = ($this->apiKey === '') ? 'Semaphore API key is not configured.' : "Phone number '{$number}' normalized to '{$cleanedNumber}', which is not a valid Philippine mobile number (must be 09XXXXXXXXX).";
            throw new RuntimeException($reason);
        }
        $fields = ['apikey' => $this->apiKey, 'number' => $cleanedNumber, 'message' => $message];
        if ($this->senderName !== '') {
            $fields['sendername'] = $this->senderName;
        }
        $body = http_build_query($fields);
        $url = $this->baseUrl . '/messages';
        $headers = ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'];
        if ($this->transport !== null) {
            $response = ($this->transport)('POST', $url, $headers, $body);
        } else {
            $response = $this->curlRequest($url, $headers, $body);
        }
        if (!is_array($response) || !isset($response['status'], $response['body'])) {
            throw new RuntimeException('Semaphore request returned an invalid transport response.');
        }
        $decoded = is_array($response['body']) ? $response['body'] : json_decode((string)$response['body'], true);
        if ((int)$response['status'] < 200 || (int)$response['status'] >= 300 || !is_array($decoded)) {
            $errorDetail = 'Semaphore SMS request failed (HTTP ' . $response['status'] . ').';
            if (is_array($decoded)) {
                if (isset($decoded['message'])) {
                    $errorDetail = (string)$decoded['message'];
                } elseif (isset($decoded['error'])) {
                    $errorDetail = is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
                } elseif (isset($decoded[0]['message'])) {
                    $errorDetail = (string)$decoded[0]['message'];
                }
            } elseif (!empty($response['body'])) {
                $errorDetail .= ' ' . strip_tags((string)$response['body']);
            }
            throw new RuntimeException($errorDetail);
        }
        if (isset($decoded[0]) && is_array($decoded[0])
            && in_array(strtolower((string)($decoded[0]['status'] ?? '')), ['failed', 'refunded'], true)) {
            $statusReason = $decoded[0]['message'] ?? $decoded[0]['status'] ?? 'not accepted';
            throw new RuntimeException('Semaphore did not accept the SMS message: ' . $statusReason);
        }
        return ['sent' => true, 'response' => $decoded];
    }

    private function curlRequest(string $url, array $headers, string $body): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false
        ]);
        $responseBody = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $errno = curl_errno($curl);
        curl_close($curl);
        if ($responseBody === false) {
            throw new RuntimeException('Semaphore connection failed: ' . $error, $errno);
        }
        return ['status' => $status, 'body' => $responseBody];
    }
}
