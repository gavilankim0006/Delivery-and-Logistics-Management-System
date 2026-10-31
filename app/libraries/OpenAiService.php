<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * GeminiService
 *
 * Handles Gemini generateContent requests and rotates across configured API keys.
 */
class OpenAiService
{
    protected array $apiKeys;
    protected string $model;
    protected string $baseUrl;
    protected int $timeout = 30;
    protected $transport;
    protected int $keyOffset = 0;

    public function __construct(?string $apiKey = null, ?string $model = null, ?string $baseUrl = null, ?callable $transport = null)
    {
        $defaultKeys = function_exists('config_item') ? (config_item('gemini_api_keys') ?: (config_item('ors_api_key') ?: '')) : '';
        $rawKeys = $apiKey ?? ($defaultKeys !== '' ? $defaultKeys : (getenv('GEMINI_API_KEYS') ?: (getenv('GEMINI_API_KEY') ?: '')));
        $this->apiKeys = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\r\n]+/', $rawKeys)))));
        $defaultModel = function_exists('config_item') ? (config_item('ai_model') ?: (getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash')) : (getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash');
        $this->model = trim($model ?? $defaultModel);
        $this->baseUrl = rtrim($baseUrl ?? (getenv('GEMINI_BASE_URL') ?: 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $this->transport = $transport;
    }

    public function isConfigured(): bool
    {
        return count($this->apiKeys) > 0;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /** Keep OpenAI-compatible response shape for existing controller and UI. */
    public function chatCompletion(array $messages, array $tools = []): array
    {
        if (!$this->isConfigured()) {
            return $this->getMockResponse($messages);
        }

        $contents = [];
        $systemParts = [];
        $pendingFunctionCalls = false;
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $text = (string)($message['content'] ?? '');
            if ($role === 'system') {
                if ($text !== '') $systemParts[] = ['text' => $text];
                continue;
            }
            if ($role === 'tool') {
                $functionResponse = ['functionResponse' => [
                    'name' => $message['name'] ?? 'tool',
                    'response' => ['result' => $text]
                ]];
                $lastIndex = array_key_last($contents);
                if ($pendingFunctionCalls && $lastIndex !== null && $contents[$lastIndex]['role'] === 'model') {
                    $contents[] = ['role' => 'user', 'parts' => [$functionResponse]];
                    $pendingFunctionCalls = false;
                } elseif ($lastIndex !== null && $contents[$lastIndex]['role'] === 'user'
                    && isset($contents[$lastIndex]['parts'][0]['functionResponse'])) {
                    $contents[$lastIndex]['parts'][] = $functionResponse;
                } else {
                    $contents[] = ['role' => 'user', 'parts' => [$functionResponse]];
                }
                continue;
            }
            if ($role === 'assistant' && !empty($message['tool_calls'])) {
                if (!empty($message['_gemini_parts']) && is_array($message['_gemini_parts'])) {
                    $parts = $message['_gemini_parts'];
                    foreach ($parts as &$part) {
                        if (isset($part['functionCall']) && is_array($part['functionCall'])) {
                            $args = $part['functionCall']['args'] ?? [];
                            $part['functionCall']['args'] = is_array($args) ? (object)$args : new stdClass();
                        }
                    }
                    unset($part);
                    $contents[] = ['role' => 'model', 'parts' => $parts];
                    $pendingFunctionCalls = true;
                } else {
                    $parts = [];
                    foreach ($message['tool_calls'] as $toolCall) {
                        $function = $toolCall['function'] ?? [];
                        $args = json_decode($function['arguments'] ?? '{}', true);
                        $parts[] = ['functionCall' => [
                            'name' => $function['name'] ?? '',
                            'args' => is_array($args) ? (object)$args : new stdClass()
                        ]];
                    }
                    if ($text !== '') array_unshift($parts, ['text' => $text]);
                    $contents[] = ['role' => 'model', 'parts' => $parts];
                    $pendingFunctionCalls = true;
                }
                continue;
            }
            $contents[] = ['role' => $role === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $text]]];
            if ($role !== 'assistant') $pendingFunctionCalls = false;
        }

        $payload = ['contents' => $contents, 'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 800]];
        if ($systemParts) $payload['systemInstruction'] = ['parts' => $systemParts];
        if ($tools) {
            $declarations = [];
            foreach ($tools as $tool) {
                $fn = $tool['function'] ?? [];
                if (!empty($fn['name'])) {
                    $declarations[] = [
                        'name' => $fn['name'],
                        'description' => $fn['description'] ?? '',
                        'parameters' => $this->normalizeSchema($fn['parameters'] ?? ['type' => 'object'])
                    ];
                }
            }
            if ($declarations) $payload['tools'] = [['functionDeclarations' => $declarations]];
        }

        $attempted = [];
        $lastError = null;
        $keyCount = count($this->apiKeys);
        for ($i = 0; $i < $keyCount; $i++) {
            $index = ($this->keyOffset + $i) % $keyCount;
            $key = $this->apiKeys[$index];
            $attempted[] = $index;
            $url = $this->baseUrl . '/models/' . rawurlencode($this->model) . ':generateContent?key=' . rawurlencode($key);
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers = ['Content-Type: application/json', 'Accept: application/json'];
            $response = $this->transport !== null
                ? ($this->transport)('POST', $url, $headers, $body)
                : $this->curlRequest('POST', $url, $headers, $body);
            if (!is_array($response) || !isset($response['status'], $response['body'])) {
                throw new RuntimeException('Gemini request returned an invalid transport response.');
            }
            $decoded = is_array($response['body']) ? $response['body'] : json_decode((string)$response['body'], true);
            $status = (int)$response['status'];
            if ($status >= 200 && $status < 300 && is_array($decoded)) {
                $this->keyOffset = $index;
                return $this->normalizeResponse($decoded);
            }
            $lastError = new RuntimeException((string)($decoded['error']['message'] ?? ('Gemini API error with HTTP ' . $status)), $status);
            // Retry another configured account only for key/quota/rate-limit failures.
            if (!in_array($status, [401, 403, 429], true)) break;
        }
        if (count($attempted) > 1 || ($lastError && in_array($lastError->getCode(), [401, 403, 429], true))) {
            $this->keyOffset = ($this->keyOffset + 1) % $keyCount;
        }
        throw $lastError ?? new RuntimeException('Gemini request failed.');
    }

    protected function normalizeSchema(array $schema): array
    {
        if (isset($schema['type']) && is_string($schema['type'])) $schema['type'] = strtoupper($schema['type']);
        foreach (['properties', 'items'] as $key) {
            if (isset($schema[$key]) && is_array($schema[$key])) {
                if ($key === 'properties') {
                    foreach ($schema[$key] as $name => $property) if (is_array($property)) $schema[$key][$name] = $this->normalizeSchema($property);
                } else $schema[$key] = $this->normalizeSchema($schema[$key]);
            }
        }
        return $schema;
    }

    protected function normalizeResponse(array $response): array
    {
        $candidate = $response['candidates'][0] ?? [];
        $content = $candidate['content'] ?? [];
        $text = '';
        $calls = [];
        $functionParts = [];
        foreach (($content['parts'] ?? []) as $part) {
            if (isset($part['text'])) $text .= $part['text'];
            if (isset($part['functionCall'])) {
                $call = $part['functionCall'];
                $functionParts[] = $part;
                $calls[] = ['id' => 'call_' . bin2hex(random_bytes(8)), 'type' => 'function', 'function' => [
                    'name' => $call['name'] ?? '',
                    'arguments' => json_encode($call['args'] ?? new stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ], '_gemini_part' => $part];
            }
        }
        $message = ['role' => 'assistant', 'content' => $text];
        if ($calls) {
            foreach ($calls as $index => &$call) $call['_gemini_part'] = $functionParts[$index] ?? null;
            unset($call);
            $message['tool_calls'] = $calls;
            $message['_gemini_parts'] = $functionParts;
        }
        return ['id' => $response['responseId'] ?? ('gemini-' . bin2hex(random_bytes(6))), 'object' => 'chat.completion', 'model' => $this->model, 'choices' => [['index' => 0, 'message' => $message, 'finish_reason' => $calls ? 'tool_calls' : 'stop']]];
    }

    protected function curlRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $this->timeout, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false]);
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        $responseBody = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);
        if ($responseBody === false) throw new RuntimeException('cURL error contacting Gemini: ' . $err);
        return ['status' => $status, 'body' => $responseBody];
    }

    protected function getMockResponse(array $messages): array
    {
        $lastUserMsg = '';
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') { $lastUserMsg = (string)($messages[$i]['content'] ?? ''); break; }
        }
        $reply = 'Dispatch AI (Preview): Gemini is not configured. Add one or more keys to GEMINI_API_KEYS in .env. Your message: ' . htmlspecialchars(substr($lastUserMsg, 0, 100));
        return ['id' => 'mock-' . bin2hex(random_bytes(6)), 'object' => 'chat.completion', 'model' => $this->model . '-mock', 'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $reply], 'finish_reason' => 'stop']]];
    }
}
