<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$webPushAutoload = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR) . 'vendor/autoload.php';
if (is_file($webPushAutoload)) {
    require_once $webPushAutoload;
}

class BrowserPushService
{
    private string $publicKey;
    private string $privateKey;
    private string $subject;

    public function __construct()
    {
        $this->publicKey = trim((string)$this->configValue('firebase_vapid_key', 'FIREBASE_VAPID_KEY'));
        $this->privateKey = trim((string)$this->configValue('webpush_vapid_private_key', 'WEBPUSH_VAPID_PRIVATE_KEY'));
        $this->subject = trim((string)$this->configValue('webpush_vapid_subject', 'WEBPUSH_VAPID_SUBJECT'));
    }

    private function configValue(string $item, string $environmentVariable): string
    {
        $configured = function_exists('config_item') ? config_item($item) : null;
        $environment = getenv($environmentVariable);
        return (string)(($configured !== null && $configured !== false && $configured !== '') ? $configured : ($environment !== false ? $environment : ''));
    }

    public function isEnabled(): bool
    {
        $enabled = function_exists('config_item') ? (bool)config_item('firebase_push_enabled') : false;
        $enabled = $enabled || getenv('FIREBASE_PUSH_ENABLED') === '1';
        return $enabled && $this->publicKey !== '' && $this->privateKey !== '' && $this->subject !== '';
    }

    public function sendToSubscription(string $subscriptionJson, string $title, string $body, array $data = []): array
    {
        if (!$this->isEnabled()) {
            return ['sent' => false, 'disabled' => true, 'error' => 'Browser Web Push is not fully configured on the server.'];
        }
        if (!class_exists('Minishlink\\WebPush\\WebPush') || !class_exists('Minishlink\\WebPush\\Subscription')) {
            return ['sent' => false, 'disabled' => true, 'error' => 'The PHP Web Push dependency is not installed. Run composer install on this server.'];
        }

        $subscriptionData = json_decode($subscriptionJson, true);
        if (!is_array($subscriptionData) || empty($subscriptionData['endpoint']) || empty($subscriptionData['keys']['p256dh']) || empty($subscriptionData['keys']['auth'])) {
            return ['sent' => false, 'invalid_subscription' => true, 'error' => 'Stored browser push subscription is invalid.'];
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $this->subject,
                    'publicKey' => $this->publicKey,
                    'privateKey' => $this->privateKey,
                ],
            ]);
            $report = $webPush->sendOneNotification(
                Subscription::create($subscriptionData),
                json_encode(['title' => $title, 'body' => $body, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ['TTL' => 300]
            );

            if ($report->isSuccess()) {
                return ['sent' => true, 'endpoint' => $report->getEndpoint()];
            }
            $status = $report->getResponse() ? $report->getResponse()->getStatusCode() : null;
            return [
                'sent' => false,
                'invalid_subscription' => in_array($status, [404, 410], true),
                'error' => $report->getReason() ?: 'Web Push delivery failed.',
                'status' => $status,
            ];
        } catch (Throwable $error) {
            return ['sent' => false, 'error' => $error->getMessage()];
        }
    }
}
