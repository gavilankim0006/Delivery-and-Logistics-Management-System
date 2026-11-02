<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/PayMongoService.php';
require_once APP_DIR . 'libraries/PaymentCheckoutService.php';
require_once APP_DIR . 'libraries/SemaphoreService.php';
require_once APP_DIR . 'libraries/EmailService.php';

class PayMongoWebhookProcessor
{
    private $db;
    private $orderModel;
    private ?PayMongoService $provider;
    private ?SemaphoreService $sms;
    private ?EmailService $email;

    public function __construct($db = null, $orderModel = null, ?PayMongoService $provider = null, ?SemaphoreService $sms = null, ?EmailService $email = null)
    {
        $lava = function_exists('lava_instance') ? lava_instance() : null;
        $this->db = $db ?? ($lava ? $lava->db : null);
        if ($orderModel) {
            $this->orderModel = $orderModel;
        } elseif ($lava) {
            $this->orderModel = $lava->Order_model ?? (isset($lava->call) ? $lava->call->model('Order_model') : null);
        }
        if ($provider !== null) {
            $this->provider = $provider;
        } else {
            try {
                $this->provider = new PayMongoService();
            } catch (Throwable $e) {
                $this->provider = null;
            }
        }
        if ($sms !== null) {
            $this->sms = $sms;
        } else {
            try {
                $this->sms = new SemaphoreService();
            } catch (Throwable $e) {
                $this->sms = null;
            }
        }
        if ($email !== null) {
            $this->email = $email;
        } else {
            try {
                $this->email = new EmailService();
            } catch (Throwable $e) {
                $this->email = null;
            }
        }
    }

    public static function verifySignature(string $rawBody, string $header, ?string $secret = null): bool
    {
        $secret = $secret ?? (getenv('PAYMONGO_WEBHOOK_SECRET') ?: '');
        if (!str_starts_with($secret, 'whsk_test_') || strlen($rawBody) > 1048576) {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }
        $signatureKey = str_starts_with($secret, 'whsk_test_') ? 'te' : 'li';
        if (!isset($parts['t'], $parts[$signatureKey]) || !ctype_digit($parts['t']) || !preg_match('/^[a-f0-9]{64}$/i', $parts[$signatureKey])) {
            return false;
        }
        $timestamp = (int)$parts['t'];
        $expected = hash_hmac('sha256', $parts['t'] . '.' . $rawBody, $secret);
        return hash_equals($expected, $parts[$signatureKey]);
    }

    public function process(array $payload): array
    {
        $event = $payload['data'] ?? [];
        if (!is_array($event)) {
            throw new InvalidArgumentException('Invalid PayMongo webhook event.');
        }
        $attributes = $event['attributes'] ?? [];
        if (!is_array($attributes)) {
            throw new InvalidArgumentException('Invalid PayMongo webhook attributes.');
        }
        $eventId = (string)($event['id'] ?? '');
        $eventType = (string)($attributes['type'] ?? '');
        if ($eventId === '' || strlen($eventId) > 150) {
            throw new InvalidArgumentException('PayMongo webhook event ID is missing or invalid.');
        }
        $sessionId = self::sessionId($attributes);
        $verifiedSession = null;
        if ($eventType === 'checkout_session.payment.paid' && $sessionId !== '') {
            if ($this->provider === null) {
                throw new RuntimeException('PayMongo reconciliation is not configured.');
            }
            $verifiedSession = $this->provider->retrieveCheckoutSession($sessionId);
        }

        $notificationId = null;
        $emailNotificationId = null;
        $result = 'ignored';
        $this->db->transaction();
        try {
            try {
                $existing = $this->db->raw(
                    'SELECT id, status FROM webhook_events WHERE provider = ? AND event_id = ? LIMIT 1 FOR UPDATE',
                    ['paymongo', $eventId]
                )->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $this->db->roll_back();
                throw $e;
            }
            if ($existing && in_array($existing['status'], ['processed', 'ignored', 'failed'], true)) {
                $this->db->commit();
                return ['duplicate' => true, 'status' => 'duplicate'];
            }
            if (!$existing) {
                try {
                    $this->db->table('webhook_events')->insert([
                        'provider' => 'paymongo',
                        'event_id' => $eventId,
                        'event_type' => $eventType !== '' ? $eventType : null,
                        'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                        'status' => 'received',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                } catch (Throwable $e) {
                    $this->db->roll_back();
                    // A concurrent delivery may have inserted this unique event first.
                    if ($this->db->raw('SELECT id FROM webhook_events WHERE provider = ? AND event_id = ? LIMIT 1', ['paymongo', $eventId])->fetch(PDO::FETCH_ASSOC)) {
                        return ['duplicate' => true, 'status' => 'duplicate'];
                    }
                    throw $e;
                }
            }

            if ($eventType === 'checkout_session.payment.paid' && $verifiedSession !== null) {
                [$verifiedId, $amount, $currency, $paymentId, $paymentMethod] = self::verifiedPayment($verifiedSession);
                if ($verifiedId !== $sessionId || $currency !== 'PHP') {
                    throw new InvalidArgumentException('PayMongo session identity or currency did not match.');
                }
                $payment = $this->db->raw(
                    "SELECT p.id, p.order_id, p.amount, p.status, p.paymongo_payment_id, o.status AS order_status, o.order_no, o.customer_id,
                            u.id AS user_id, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.sms_opt_in, u.email_notifications_opt_in
                     FROM payments p INNER JOIN orders o ON o.id = p.order_id
                     INNER JOIN customers c ON c.id = o.customer_id INNER JOIN users u ON u.id = c.user_id
                     WHERE p.paymongo_session_id = ? LIMIT 1 FOR UPDATE",
                    [$sessionId]
                )->fetch(PDO::FETCH_ASSOC);
                if ($payment && $payment['status'] === 'paid') {
                    $result = 'already_paid';
                }
                $orderCanBePaid = $payment && $payment['order_status'] === 'pending_payment';
                if ($payment && $payment['status'] === 'pending' && $orderCanBePaid
                    && PaymentCheckoutService::toCentavos((string)$payment['amount']) === $amount) {
                    $now = date('Y-m-d H:i:s');
                    $this->db->raw(
                        "UPDATE payments SET status = 'paid', paymongo_payment_id = ?, payment_method = ?, paid_at = ?, updated_at = ? WHERE id = ? AND status = 'pending'",
                        [$paymentId, $paymentMethod, $now, $now, (int)$payment['id']]
                    );
                    if ($payment['order_status'] === 'pending_payment') {
                        $this->orderModel->set_status((int)$payment['order_id'], 'paid', 'PayMongo confirmed payment.', 'system', null);
                        $result = 'paid';

                        $message = 'Payment confirmed for delivery order ' . $payment['order_no'] . '.';

                        // Enqueue SMS if opted in
                        if ((int)$payment['sms_opt_in'] === 1 && trim((string)$payment['user_phone']) !== '' && getenv('SEMAPHORE_ENABLED') === '1') {
                            $this->db->raw(
                                "INSERT INTO notifications (user_id, order_id, channel, recipient, message, status, created_at) VALUES (?, ?, 'sms', ?, ?, 'pending', ?)",
                                [(int)$payment['user_id'], (int)$payment['order_id'], $payment['user_phone'], $message, $now]
                            );
                            $notificationId = (int)$this->db->last_id();
                        }

                        // Enqueue Email if email is present and opted in (defaults to enabled if SMS opted in or email notifications active)
                        $emailOptIn = isset($payment['email_notifications_opt_in'])
                            ? (int)$payment['email_notifications_opt_in'] === 1
                            : ((int)($payment['sms_opt_in'] ?? 0) === 1);

                        if ($emailOptIn && !empty($payment['user_email']) && filter_var($payment['user_email'], FILTER_VALIDATE_EMAIL)) {
                            $this->db->raw(
                                "INSERT INTO notifications (user_id, order_id, channel, recipient, message, status, created_at) VALUES (?, ?, 'email', ?, ?, 'pending', ?)",
                                [(int)$payment['user_id'], (int)$payment['order_id'], trim($payment['user_email']), $message, $now]
                            );
                            $emailNotificationId = (int)$this->db->last_id();
                        }
                    }
                } elseif ($payment && $payment['status'] === 'pending'
                    && PaymentCheckoutService::toCentavos((string)$payment['amount']) === $amount
                    && $payment['order_status'] !== 'pending_payment') {
                    // A paid checkout for a cancelled/non-payable order requires manual refund/reconciliation.
                    $now = date('Y-m-d H:i:s');
                    $this->db->raw(
                        "UPDATE payments SET status = 'paid', paymongo_payment_id = ?, payment_method = ?, paid_at = ?, updated_at = ? WHERE id = ? AND status = 'pending'",
                        [$paymentId, $paymentMethod, $now, $now, (int)$payment['id']]
                    );
                    $this->db->raw(
                        'INSERT INTO notifications (user_id, order_id, channel, recipient, message, status, provider_response, created_at) VALUES (?, ?, \'sms\', ?, ?, \'failed\', ?, ?)',
                        [(int)$payment['user_id'], (int)$payment['order_id'], '', 'PayMongo paid an order that is no longer payable; manual refund/reconciliation required.', 'SMS suppressed: manual reconciliation required.', $now]
                    );
                    $result = 'payment_received_order_not_payable';
                }
            }

            $eventStatus = $result === 'ignored' ? 'ignored' : 'processed';
            $this->db->raw(
                'UPDATE webhook_events SET status = ?, processed_at = ? WHERE provider = ? AND event_id = ?',
                [$eventStatus, date('Y-m-d H:i:s'), 'paymongo', $eventId]
            );
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->roll_back();
            throw $e;
        }

        if ($notificationId !== null && $this->sms !== null) {
            $this->dispatchSms($notificationId);
        } elseif ($notificationId !== null) {
            $this->db->raw("UPDATE notifications SET status = 'failed', provider_response = ?, sent_at = ? WHERE id = ?", ['Semaphore is not configured.', date('Y-m-d H:i:s'), $notificationId]);
        }

        if ($emailNotificationId !== null && $this->email !== null) {
            $this->dispatchEmail($emailNotificationId, [
                'order_id' => (int)($payment['order_id'] ?? 0),
                'order_no' => (string)($payment['order_no'] ?? ''),
                'customer_name' => (string)($payment['user_name'] ?? 'Valued Customer'),
                'amount' => (string)($payment['amount'] ?? '0.00'),
                'payment_method' => (string)($paymentMethod ?? 'PayMongo Online Payment'),
            ]);
        } elseif ($emailNotificationId !== null) {
            $this->db->raw("UPDATE notifications SET status = 'failed', provider_response = ?, sent_at = ? WHERE id = ?", ['Email service is not configured.', date('Y-m-d H:i:s'), $emailNotificationId]);
        }

        return ['duplicate' => false, 'status' => $result];
    }

    private function dispatchEmail(int $notificationId, array $context = []): void
    {
        $row = $this->db->raw('SELECT recipient, message FROM notifications WHERE id = ? LIMIT 1', [$notificationId])->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        try {
            $response = $this->email->sendPaymentConfirmation(
                (string)$row['recipient'],
                (string)($context['customer_name'] ?? 'Valued Customer'),
                (string)($context['order_no'] ?? ''),
                (string)($context['amount'] ?? '0.00'),
                (string)($context['payment_method'] ?? 'Online Payment'),
                ['order_id' => (int)($context['order_id'] ?? 0)]
            );
            $status = !empty($response['success']) ? 'sent' : 'failed';
            $providerResponse = !empty($response['success']) ? ($response['message'] ?? 'Email sent.') : ($response['error'] ?? 'Email failed.');
        } catch (Throwable $e) {
            $status = 'failed';
            $providerResponse = substr($e->getMessage(), 0, 1000);
            error_log('Payment confirmation email failed: ' . $e->getMessage());
        }
        $this->db->raw(
            'UPDATE notifications SET status = ?, provider_response = ?, sent_at = ? WHERE id = ?',
            [$status, $providerResponse, date('Y-m-d H:i:s'), $notificationId]
        );
    }

    private function dispatchSms(int $notificationId): void
    {
        $row = $this->db->raw('SELECT recipient, message FROM notifications WHERE id = ? LIMIT 1', [$notificationId])->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        try {
            $response = $this->sms->send((string)$row['recipient'], (string)$row['message']);
            $status = !empty($response['sent']) ? 'sent' : 'failed';
            $providerResponse = $response['sent'] ? json_encode($response['response'], JSON_UNESCAPED_SLASHES) : 'SMS sending disabled.';
        } catch (Throwable $e) {
            $status = 'failed';
            $providerResponse = substr($e->getMessage(), 0, 1000);
            error_log('Payment confirmation SMS failed: ' . $e->getMessage());
        }
        $this->db->raw(
            'UPDATE notifications SET status = ?, provider_response = ?, sent_at = ? WHERE id = ?',
            [$status, $providerResponse, date('Y-m-d H:i:s'), $notificationId]
        );
    }

    private static function sessionId(array $attributes): string
    {
        $session = $attributes['data'] ?? [];
        if (isset($session['id']) && ($session['type'] ?? '') === 'checkout_session') {
            return (string)$session['id'];
        }
        $inner = $session['attributes'] ?? [];
        return (string)($inner['id'] ?? '');
    }

    private static function verifiedPayment(array $response): array
    {
        $data = $response['data'] ?? $response;
        $attributes = $data['attributes'] ?? [];
        $sessionId = (string)($data['id'] ?? '');
        $currency = strtoupper((string)($attributes['currency'] ?? ''));
        $paidPayments = [];
        foreach ((array)($attributes['payments'] ?? []) as $payment) {
            $paymentAttributes = $payment['attributes'] ?? [];
            if (($paymentAttributes['status'] ?? '') === 'paid' && !empty($payment['id'])) {
                $paidPayments[] = ['id' => (string)$payment['id'], 'attributes' => $paymentAttributes];
            }
        }
        if (!$paidPayments) {
            throw new InvalidArgumentException('PayMongo did not return a paid payment record for this session.');
        }
        $amount = array_sum(array_map(static fn($item) => (int)($item['attributes']['amount'] ?? 0), $paidPayments));
        $paymentId = $paidPayments[0]['id'];
        $currency = strtoupper((string)($paidPayments[0]['attributes']['currency'] ?? $currency));
        $paymentMethod = '';
        $source = $paidPayments[0]['attributes']['source'] ?? [];
        if (is_array($source)) {
            $paymentMethod = (string)($source['type'] ?? '');
        }
        if ($sessionId === '' || $amount < 1) {
            throw new InvalidArgumentException('PayMongo has not confirmed a paid checkout session.');
        }
        return [$sessionId, $amount, $currency, $paymentId, $paymentMethod];
    }
}
