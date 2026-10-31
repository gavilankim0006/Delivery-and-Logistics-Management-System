<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Firebase Cloud Messaging (FCM) HTTP v1 API Service
 *
 * Implements push notifications via Google FCM HTTP v1 using
 * OAuth2 service-account JWT authentication signed with RS256.
 */
class FirebasePushService
{
    private string $projectId;
    private string $clientEmail;
    private string $privateKey;
    private $transport;
    private ?string $cachedAccessToken = null;
    private int $tokenExpiresAt = 0;

    public function __construct(
        ?string $projectId = null,
        ?string $clientEmail = null,
        ?string $privateKey = null,
        ?string $credentialsPath = null,
        ?callable $transport = null
    ) {
        $this->transport = $transport;

        // Resolve credentials file path if provided or set in env/config
        $defaultCredsPath = function_exists('config_item') ? (config_item('firebase_credentials_path') ?: (getenv('FIREBASE_CREDENTIALS_PATH') ?: '')) : (getenv('FIREBASE_CREDENTIALS_PATH') ?: '');
        $resolvedPath = $credentialsPath ?? $defaultCredsPath;
        if ($resolvedPath === '' && defined('APP_DIR')) {
            $defaultJson = dirname(APP_DIR) . DIRECTORY_SEPARATOR . 'delivery-and-logistics-system-firebase-adminsdk-fbsvc-0a9478f6dd.json';
            if (file_exists($defaultJson)) {
                $resolvedPath = $defaultJson;
            }
        }

        $fileCredentials = [];
        if ($resolvedPath !== '' && file_exists($resolvedPath)) {
            $jsonContent = file_get_contents($resolvedPath);
            if ($jsonContent !== false) {
                $parsed = json_decode($jsonContent, true);
                if (is_array($parsed)) {
                    $fileCredentials = $parsed;
                }
            }
        }

        $defaultProj = function_exists('config_item') ? (config_item('firebase_project_id') ?: (getenv('FIREBASE_PROJECT_ID') ?: '')) : (getenv('FIREBASE_PROJECT_ID') ?: '');
        $defaultEmail = function_exists('config_item') ? (config_item('firebase_client_email') ?: (getenv('FIREBASE_CLIENT_EMAIL') ?: '')) : (getenv('FIREBASE_CLIENT_EMAIL') ?: '');
        $defaultKey = function_exists('config_item') ? (config_item('firebase_private_key') ?: (getenv('FIREBASE_PRIVATE_KEY') ?: '')) : (getenv('FIREBASE_PRIVATE_KEY') ?: '');

        $this->projectId = trim($projectId ?? ($fileCredentials['project_id'] ?? $defaultProj));
        $this->clientEmail = trim($clientEmail ?? ($fileCredentials['client_email'] ?? $defaultEmail));

        $rawKey = $privateKey ?? ($fileCredentials['private_key'] ?? $defaultKey);
        $this->privateKey = trim(str_replace('\n', "\n", $rawKey));
    }

    /**
     * Check if Firebase push notifications are enabled
     */
    public function isEnabled(): bool
    {
        $configEnabled = function_exists('config_item') ? (bool)config_item('firebase_push_enabled') : false;
        return $configEnabled || getenv('FIREBASE_PUSH_ENABLED') === '1';
    }

    /**
     * Manually set or override the cached OAuth access token (useful for unit testing)
     */
    public function setAccessToken(string $token, int $expiresIn = 3600): self
    {
        $this->cachedAccessToken = $token;
        $this->tokenExpiresAt = time() + $expiresIn;
        return $this;
    }

    /**
     * Get a valid OAuth2 access token, requesting a new one via signed JWT if expired or not set
     */
    public function getAccessToken(): string
    {
        if ($this->cachedAccessToken !== null && time() < ($this->tokenExpiresAt - 60)) {
            return $this->cachedAccessToken;
        }

        if (empty($this->clientEmail) || empty($this->privateKey)) {
            throw new RuntimeException('Firebase push credentials (client_email or private_key) are missing.');
        }

        $jwt = $this->generateJwt();
        $url = 'https://oauth2.googleapis.com/token';
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ];
        $body = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        $response = $this->executeRequest('POST', $url, $headers, $body);
        if (!is_array($response) || !isset($response['status'], $response['body'])) {
            throw new RuntimeException('OAuth token request returned an invalid transport response.');
        }

        $decoded = is_array($response['body']) ? $response['body'] : json_decode((string)$response['body'], true);
        if ((int)$response['status'] !== 200 || !is_array($decoded) || !isset($decoded['access_token'])) {
            $error = is_array($decoded) ? ($decoded['error_description'] ?? ($decoded['error'] ?? 'Unknown OAuth error')) : 'HTTP ' . $response['status'];
            throw new RuntimeException('Failed to obtain Google OAuth access token: ' . $error);
        }

        $this->cachedAccessToken = (string)$decoded['access_token'];
        $expiresIn = isset($decoded['expires_in']) ? (int)$decoded['expires_in'] : 3600;
        $this->tokenExpiresAt = time() + $expiresIn;

        return $this->cachedAccessToken;
    }

    /**
     * Send a notification message to a single device FCM token
     *
     * @param string $fcmToken Target device registration token
     * @param string $title Notification title
     * @param string $body Notification body text
     * @param array $data Optional key-value data payload (all values converted to strings)
     * @return array Result array ['sent' => bool, ...]
     */
    public function sendToToken(string $fcmToken, string $title, string $body, array $data = []): array
    {
        if (!$this->isEnabled()) {
            return ['sent' => false, 'disabled' => true];
        }

        $trimmedToken = trim($fcmToken);
        if ($trimmedToken === '') {
            return [
                'sent' => false,
                'invalid_token' => true,
                'error' => 'FCM registration token cannot be empty.',
            ];
        }

        if (empty($this->projectId)) {
            throw new RuntimeException('Firebase project_id is missing.');
        }

        $accessToken = $this->getAccessToken();
        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($this->projectId) . '/messages:send';

        $message = [
            'token' => $trimmedToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
        ];

        if (!empty($data)) {
            $stringData = [];
            foreach ($data as $k => $v) {
                $stringData[(string)$k] = is_scalar($v) ? (string)$v : json_encode($v);
            }
            $message['data'] = $stringData;
        }

        $payload = ['message' => $message];
        $jsonBody = json_encode($payload);
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8',
            'Accept: application/json',
        ];

        $response = $this->executeRequest('POST', $url, $headers, $jsonBody);
        if (!is_array($response) || !isset($response['status'], $response['body'])) {
            throw new RuntimeException('Firebase push request returned an invalid transport response.');
        }

        $status = (int)$response['status'];
        $decoded = is_array($response['body']) ? $response['body'] : json_decode((string)$response['body'], true);

        if ($status >= 200 && $status < 300) {
            return [
                'sent' => true,
                'message_id' => $decoded['name'] ?? null,
                'response' => $decoded,
            ];
        }

        // Check if token is invalid or unregistered
        $isInvalidToken = false;
        $errorMessage = 'FCM request failed with status ' . $status;
        $errorCode = null;

        if (is_array($decoded) && isset($decoded['error'])) {
            $err = $decoded['error'];
            $errorMessage = (string)($err['message'] ?? $errorMessage);
            $errStatus = (string)($err['status'] ?? '');

            if (in_array($errStatus, ['NOT_FOUND', 'INVALID_ARGUMENT'], true)) {
                $isInvalidToken = true;
            }

            if (!empty($err['details']) && is_array($err['details'])) {
                foreach ($err['details'] as $detail) {
                    $code = (string)($detail['errorCode'] ?? '');
                    if (in_array($code, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                        $isInvalidToken = true;
                        $errorCode = $code;
                        break;
                    }
                }
            }

            if (stripos($errorMessage, 'unregistered') !== false || stripos($errorMessage, 'not a valid FCM registration token') !== false) {
                $isInvalidToken = true;
            }
        }

        if ($isInvalidToken) {
            return [
                'sent' => false,
                'invalid_token' => true,
                'error' => $errorMessage,
                'error_code' => $errorCode ?? 'UNREGISTERED',
                'response' => $decoded,
            ];
        }

        throw new RuntimeException('Firebase push request failed: ' . $errorMessage);
    }

    /**
     * Send notification to multiple device tokens
     *
     * @param array $tokens List of FCM registration tokens
     * @param string $title Notification title
     * @param string $body Notification body text
     * @param array $data Optional data payload
     * @return array Summary with per-token results
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        if (!$this->isEnabled()) {
            return ['sent' => false, 'disabled' => true];
        }

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($tokens as $token) {
            $tokenStr = (string)$token;
            try {
                $res = $this->sendToToken($tokenStr, $title, $body, $data);
                $results[$tokenStr] = $res;
                if (!empty($res['sent'])) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            } catch (\Throwable $e) {
                $results[$tokenStr] = [
                    'sent' => false,
                    'error' => $e->getMessage(),
                ];
                $failureCount++;
            }
        }

        return [
            'sent' => $successCount > 0,
            'total' => count($tokens),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results,
        ];
    }

    private function executeRequest(string $method, string $url, array $headers, string $body): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($method, $url, $headers, $body);
        }
        return $this->curlRequest($url, $headers, $body);
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
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $responseBody = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $errno = curl_errno($curl);
        curl_close($curl);

        if ($responseBody === false) {
            throw new RuntimeException('FCM connection failed: ' . $error, $errno);
        }

        return ['status' => $status, 'body' => $responseBody];
    }

    private function generateJwt(): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $now = time();
        $claims = [
            'iss' => $this->clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $encodedHeader = self::base64UrlEncode((string)json_encode($header));
        $encodedClaims = self::base64UrlEncode((string)json_encode($claims));
        $signingInput = $encodedHeader . '.' . $encodedClaims;

        $privateKey = openssl_pkey_get_private($this->privateKey);
        if ($privateKey === false) {
            throw new RuntimeException('Invalid Firebase private key for openssl.');
        }

        $signature = '';
        $success = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$success) {
            throw new RuntimeException('Failed to sign JWT with Firebase private key.');
        }

        return $signingInput . '.' . self::base64UrlEncode($signature);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
