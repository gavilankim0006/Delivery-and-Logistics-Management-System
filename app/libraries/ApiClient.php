<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * ApiClient Base Service Class
 *
 * Implements cURL HTTP client with 10s timeout, automatic 2x retry on 5xx or timeout,
 * and comprehensive request/response audit logging to the `api_logs` database table.
 */
class ApiClient
{
    /**
     * Service identifier for logging (e.g. 'google_maps', 'lalamove', 'paymongo', 'semaphore', 'fcm')
     *
     * @var string
     */
    protected string $serviceName = 'generic';

    /**
     * Base URL for the service
     *
     * @var string
     */
    protected string $baseUrl = '';

    /**
     * Default timeout in seconds
     *
     * @var int
     */
    protected int $timeout = 10;

    /**
     * Maximum retry attempts on 5xx errors or timeouts
     *
     * @var int
     */
    protected int $maxRetries = 2;

    /**
     * Delay between retries in microseconds (default: 200ms)
     *
     * @var int
     */
    protected int $retryDelayUs = 200000;

    /**
     * Database connection instance for logging
     *
     * @var object|null
     */
    protected static $db = null;

    /**
     * Constructor
     *
     * @param string $serviceName
     * @param string $baseUrl
     */
    public function __construct(string $serviceName = 'generic', string $baseUrl = '')
    {
        if (!empty($serviceName)) {
            $this->serviceName = $serviceName;
        }
        if (!empty($baseUrl)) {
            $this->baseUrl = rtrim($baseUrl, '/');
        }
    }

    /**
     * Get or initialize Database instance
     *
     * @return object|null
     */
    protected function getDb()
    {
        if (self::$db === null) {
            try {
                if (class_exists('Database')) {
                    self::$db = Database::instance(null);
                } else {
                    $dbFile = SYSTEM_DIR . 'database/Database.php';
                    if (file_exists($dbFile)) {
                        require_once $dbFile;
                        self::$db = Database::instance(null);
                    }
                }
            } catch (\Throwable $e) {
                $this->fallbackLog('Failed to initialize database for ApiClient: ' . $e->getMessage());
                return null;
            }
        }
        return self::$db;
    }

    /**
     * Perform HTTP GET request
     *
     * @param string $endpoint
     * @param array $queryParams
     * @param array $headers
     * @return array
     */
    public function get(string $endpoint, array $queryParams = [], array $headers = []): array
    {
        $url = $this->buildUrl($endpoint, $queryParams);
        return $this->request('GET', $url, null, $headers);
    }

    /**
     * Perform HTTP POST request
     *
     * @param string $endpoint
     * @param mixed $body
     * @param array $headers
     * @return array
     */
    public function post(string $endpoint, $body = null, array $headers = []): array
    {
        $url = $this->buildUrl($endpoint);
        return $this->request('POST', $url, $body, $headers);
    }

    /**
     * Perform HTTP PUT request
     *
     * @param string $endpoint
     * @param mixed $body
     * @param array $headers
     * @return array
     */
    public function put(string $endpoint, $body = null, array $headers = []): array
    {
        $url = $this->buildUrl($endpoint);
        return $this->request('PUT', $url, $body, $headers);
    }

    /**
     * Perform HTTP PATCH request
     *
     * @param string $endpoint
     * @param mixed $body
     * @param array $headers
     * @return array
     */
    public function patch(string $endpoint, $body = null, array $headers = []): array
    {
        $url = $this->buildUrl($endpoint);
        return $this->request('PATCH', $url, $body, $headers);
    }

    /**
     * Perform HTTP DELETE request
     *
     * @param string $endpoint
     * @param mixed $body
     * @param array $headers
     * @return array
     */
    public function delete(string $endpoint, $body = null, array $headers = []): array
    {
        $url = $this->buildUrl($endpoint);
        return $this->request('DELETE', $url, $body, $headers);
    }

    /**
     * Build full URL with query parameters
     *
     * @param string $endpoint
     * @param array $queryParams
     * @return string
     */
    protected function buildUrl(string $endpoint, array $queryParams = []): string
    {
        if (preg_match('#^https?://#i', $endpoint)) {
            $url = $endpoint;
        } else {
            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        }

        if (!empty($queryParams)) {
            $queryStr = http_build_query($queryParams);
            $url .= (str_contains($url, '?') ? '&' : '?') . $queryStr;
        }

        return $url;
    }

    /**
     * Core request executor with retry logic and audit logging
     *
     * @param string $method
     * @param string $url
     * @param mixed $body
     * @param array $headers
     * @return array
     */
    public function request(string $method, string $url, $body = null, array $headers = []): array
    {
        $method = strtoupper($method);
        $serializedBody = $this->serializeBody($body, $headers);

        $attempt = 0;
        $maxAttempts = 1 + $this->maxRetries; // 1 initial + 2 retries = 3 attempts
        $lastResult = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $startTime = microtime(true);

            $result = $this->executeCurl($method, $url, $serializedBody, $headers);
            $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

            $result['attempt'] = $attempt;
            $result['execution_time_ms'] = $elapsedMs;

            // Log this attempt to database
            $this->logCall(
                $this->serviceName,
                $method,
                $url,
                $headers,
                $serializedBody,
                $result['status'],
                $result['response_headers_raw'] ?? '',
                $result['raw_body'] ?? '',
                $elapsedMs,
                $result['error'],
                $attempt
            );

            // Determine if retry is needed: 5xx server error or timeout/network error
            $is5xx = ($result['status'] >= 500 && $result['status'] < 600);
            $isTimeoutOrNetwork = ($result['curl_errno'] === 28 || $result['curl_errno'] === 7 || $result['curl_errno'] === 56);

            $shouldRetry = ($is5xx || $isTimeoutOrNetwork) && ($attempt < $maxAttempts);

            if ($shouldRetry) {
                // Exponential backoff: retry 1: 200ms, retry 2: 400ms
                $delay = $this->retryDelayUs * $attempt;
                usleep($delay);
                continue;
            }

            return $result;
        }

        return $lastResult ?? [
            'success' => false,
            'status' => 0,
            'data' => null,
            'headers' => [],
            'raw_body' => '',
            'error' => 'Max retries exceeded without response',
            'attempt' => $attempt,
            'execution_time_ms' => 0
        ];
    }

    /**
     * Execute single cURL call
     *
     * @param string $method
     * @param string $url
     * @param string|null $body
     * @param array $headers
     * @return array
     */
    protected function executeCurl(string $method, string $url, ?string $body, array $headers): array
    {
        $ch = curl_init();

        $formattedHeaders = [];
        foreach ($headers as $key => $val) {
            if (is_int($key)) {
                $formattedHeaders[] = $val;
            } else {
                $formattedHeaders[] = "{$key}: {$val}";
            }
        }

        // Add User-Agent if not set
        $hasUserAgent = false;
        foreach ($formattedHeaders as $h) {
            if (stripos($h, 'user-agent:') === 0) {
                $hasUserAgent = true;
                break;
            }
        }
        if (!$hasUserAgent) {
            $formattedHeaders[] = 'User-Agent: DeliveryLogisticsApp/1.0';
        }

        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $headerParts = explode(':', $header, 2);
                if (count($headerParts) === 2) {
                    $responseHeaders[trim($headerParts[0])] = trim($headerParts[1]);
                }
                return $len;
            }
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawResponse = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $parsedData = null;
        if ($rawResponse !== false && $rawResponse !== '') {
            $json = json_decode($rawResponse, true);
            $parsedData = (json_last_error() === JSON_ERROR_NONE) ? $json : null;
        }

        $success = ($statusCode >= 200 && $statusCode < 300);

        return [
            'success' => $success,
            'status' => $statusCode,
            'data' => $parsedData,
            'headers' => $responseHeaders,
            'response_headers_raw' => json_encode($responseHeaders),
            'raw_body' => $rawResponse !== false ? (string) $rawResponse : '',
            'error' => ($curlErrno !== 0) ? "cURL [{$curlErrno}]: {$curlError}" : ($success ? null : "HTTP {$statusCode}"),
            'curl_errno' => $curlErrno,
        ];
    }

    /**
     * Serialize request body to string
     *
     * @param mixed $body
     * @param array $headers
     * @return string|null
     */
    protected function serializeBody($body, array &$headers): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_string($body)) {
            return $body;
        }

        if (is_array($body) || is_object($body)) {
            // Check if Content-Type is specified
            $isJson = true;
            foreach ($headers as $k => $v) {
                $headerStr = is_int($k) ? $v : "{$k}: {$v}";
                if (stripos($headerStr, 'content-type:') === 0 && stripos($headerStr, 'application/x-www-form-urlencoded') !== false) {
                    $isJson = false;
                    break;
                }
            }

            if ($isJson) {
                // Ensure Content-Type is set to application/json
                $hasContentType = false;
                foreach ($headers as $k => $v) {
                    $headerStr = is_int($k) ? $v : "{$k}: {$v}";
                    if (stripos($headerStr, 'content-type:') === 0) {
                        $hasContentType = true;
                        break;
                    }
                }
                if (!$hasContentType) {
                    $headers[] = 'Content-Type: application/json';
                }
                return json_encode($body);
            } else {
                return http_build_query((array) $body);
            }
        }

        return (string) $body;
    }

    /**
     * Log API call to `api_logs` table
     *
     * @param string $service
     * @param string $method
     * @param string $endpoint
     * @param array $headers
     * @param string|null $body
     * @param int $status
     * @param string $responseHeaders
     * @param string $responseBody
     * @param int $executionTimeMs
     * @param string|null $error
     * @param int $attempt
     * @return void
     */
    protected function logCall(
        string $service,
        string $method,
        string $endpoint,
        array $headers,
        ?string $body,
        int $status,
        string $responseHeaders,
        string $responseBody,
        int $executionTimeMs,
        ?string $error,
        int $attempt
    ): void {
        try {
            $db = $this->getDb();
            if (!$db) {
                throw new \Exception('No database instance available');
            }

            // Mask sensitive authorization / secret headers for security
            $maskedHeaders = $this->maskHeaders($headers);
            $maskedBody = $this->maskSensitiveFields($body);

            // Truncate response body if huge to avoid DB bloat (> 500KB)
            $safeResponseBody = strlen($responseBody) > 500000 ? substr($responseBody, 0, 500000) . '...[TRUNCATED]' : $responseBody;

            $logData = [
                'service' => substr($service, 0, 50),
                'method' => substr($method, 0, 10),
                'endpoint' => substr($endpoint, 0, 500),
                'request_headers' => json_encode($maskedHeaders),
                'request_body' => $maskedBody,
                'response_status' => $status,
                'response_headers' => $responseHeaders,
                'response_body' => $safeResponseBody,
                'execution_time_ms' => $executionTimeMs,
                'error_message' => $error ? substr($error, 0, 1000) : null,
                'attempt' => $attempt,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $db->table('api_logs')->insert($logData);
        } catch (\Throwable $e) {
            $this->fallbackLog(sprintf(
                "[%s] %s %s | Status: %d | Attempt: %d | Time: %dms | Log Error: %s",
                date('Y-m-d H:i:s'),
                $method,
                $endpoint,
                $status,
                $attempt,
                $executionTimeMs,
                $e->getMessage()
            ));
        }
    }

    /**
     * Mask sensitive headers such as Authorization tokens and API keys
     *
     * @param array $headers
     * @return array
     */
    protected function maskHeaders(array $headers): array
    {
        $masked = [];
        $sensitiveKeys = ['authorization', 'x-api-key', 'secret', 'apikey', 'key', 'token'];

        foreach ($headers as $k => $v) {
            if (is_int($k)) {
                $parts = explode(':', $v, 2);
                if (count($parts) === 2) {
                    $headerName = strtolower(trim($parts[0]));
                    $headerVal = trim($parts[1]);
                    if ($this->matchesAny($headerName, $sensitiveKeys)) {
                        $masked[] = trim($parts[0]) . ': ' . $this->maskValue($headerVal);
                    } else {
                        $masked[] = $v;
                    }
                } else {
                    $masked[] = $v;
                }
            } else {
                $headerName = strtolower(trim($k));
                if ($this->matchesAny($headerName, $sensitiveKeys)) {
                    $masked[$k] = $this->maskValue((string) $v);
                } else {
                    $masked[$k] = $v;
                }
            }
        }

        return $masked;
    }

    /**
     * Mask sensitive fields inside JSON request body
     *
     * @param string|null $body
     * @return string|null
     */
    protected function maskSensitiveFields(?string $body): ?string
    {
        if (empty($body)) {
            return $body;
        }

        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $sensitiveKeys = ['password', 'secret', 'cvv', 'card_number', 'private_key', 'api_key'];
            array_walk_recursive($decoded, function (&$val, $key) use ($sensitiveKeys) {
                if ($this->matchesAny(strtolower((string) $key), $sensitiveKeys) && is_string($val)) {
                    $val = '***REDACTED***';
                }
            });
            return json_encode($decoded);
        }

        return $body;
    }

    /**
     * Helper to test if a key matches any sensitive keywords
     *
     * @param string $key
     * @param array $patterns
     * @return bool
     */
    protected function matchesAny(string $key, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($key, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Mask a string showing only first and last characters
     *
     * @param string $val
     * @return string
     */
    protected function maskValue(string $val): string
    {
        $len = strlen($val);
        if ($len <= 8) {
            return '********';
        }
        return substr($val, 0, 4) . '...' . substr($val, -4);
    }

    /**
     * Fallback file logging if DB is unavailable
     *
     * @param string $msg
     * @return void
     */
    protected function fallbackLog(string $msg): void
    {
        $dir = defined('RUNTIME_DIR') ? RUNTIME_DIR : (defined('ROOT_DIR') ? ROOT_DIR . 'runtime' : dirname(__DIR__, 2) . '/runtime');
        $logFile = $dir . '/logs/api_fallback.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
    }
}
