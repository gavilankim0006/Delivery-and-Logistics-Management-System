<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/CourierWebhookProcessor.php';

class Webhooks extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('Order_model');
    }

    public function lalamove()
    {
        header('Content-Type: application/json');
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return $this->respond(405, ['success' => false, 'error' => 'Method Not Allowed']);
        }

        $secret = getenv('LALAMOVE_WEBHOOK_SECRET') ?: '';
        if (!self::inboundProcessingEnabled($secret)) {
            return $this->respond(503, ['success' => false, 'error' => 'Real courier webhook processing is disabled until production authentication is confirmed.']);
        }

        $rawBody = file_get_contents('php://input');
        $providedSignature = $_SERVER['HTTP_X_LALAMOVE_SIGNATURE'] ?? '';
        // Lalamove's public v3 guide documents request signing, not a webhook signature header.
        // This receiver uses an explicitly configured shared-secret HMAC convention until the partner webhook spec is available.
        $expectedSignature = hash_hmac('sha256', (string)$rawBody, $secret);
        if ($providedSignature === '' || !hash_equals($expectedSignature, $providedSignature)) {
            return $this->respond(401, ['success' => false, 'error' => 'Invalid webhook signature.']);
        }

        $payload = json_decode((string)$rawBody, true);
        if (!is_array($payload)) {
            return $this->respond(400, ['success' => false, 'error' => 'Invalid JSON payload.']);
        }

        try {
            $result = self::processPayload($this->db, $this->Order_model, $payload, 'system', null, true);
            return $this->respond(200, ['success' => true, 'data' => $result]);
        } catch (InvalidArgumentException $e) {
            return $this->respond(400, ['success' => false, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('Lalamove webhook processing failed: ' . $e->getMessage());
            return $this->respond(500, ['success' => false, 'error' => 'Webhook processing failed.']);
        }
    }

    public static function inboundProcessingEnabled(?string $secret = null): bool
    {
        $driver = strtolower(trim(getenv('COURIER_DRIVER') ?: 'mock'));
        $environment = strtolower(trim(getenv('APP_ENV') ?: 'development'));
        $verifiedPartnerAuth = getenv('LALAMOVE_WEBHOOK_AUTH_CONFIRMED') === '1';
        $secret = $secret ?? (getenv('LALAMOVE_WEBHOOK_SECRET') ?: '');
        return $driver === 'lalamove'
            && $environment === 'production'
            && $verifiedPartnerAuth
            && $secret !== '';
    }

    public static function processPayload($db, $orderModel, array $payload, string $actorType = 'system', ?int $actorId = null, bool $persistEvent = true): array
    {
        return (new CourierWebhookProcessor($db, $orderModel))->process($payload, $actorType, $actorId, $persistEvent);
    }

    private function respond(int $status, array $body): void
    {
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
