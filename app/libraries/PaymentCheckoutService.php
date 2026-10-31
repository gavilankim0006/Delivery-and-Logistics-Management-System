<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/PayMongoService.php';

class PaymentCheckoutService
{
    private $db;
    private $orderModel;
    private PayMongoService $provider;

    public function __construct($db = null, $orderModel = null, ?PayMongoService $provider = null)
    {
        $lava = function_exists('lava_instance') ? lava_instance() : null;
        $this->db = $db ?? ($lava ? $lava->db : null);
        if ($orderModel) {
            $this->orderModel = $orderModel;
        } elseif ($lava) {
            $this->orderModel = $lava->Order_model ?? (isset($lava->call) ? $lava->call->model('Order_model') : null);
        }
        $this->provider = $provider ?? new PayMongoService();
    }

    public function start(int $orderId, int $customerId, string $successUrl, string $cancelUrl): array
    {
        $this->db->transaction();
        try {
            $order = $this->db->raw(
                'SELECT id, order_no, status, delivery_fee FROM orders WHERE id = ? AND customer_id = ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                [$orderId, $customerId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$order || !in_array($order['status'], ['draft', 'pending_payment'], true)) {
                throw new DomainException('This order is not available for payment.');
            }

            $existing = $this->db->raw(
                "SELECT id, paymongo_session_id, checkout_url FROM payments WHERE order_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$orderId]
            )->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                if (!empty($existing['checkout_url']) && !empty($existing['paymongo_session_id'])) {
                    $this->db->commit();
                    return ['checkout_url' => $existing['checkout_url'], 'reused' => true];
                }
                throw new RuntimeException('A payment attempt is still being reconciled. Please contact support before retrying.');
            }

            $amount = self::toCentavos((string)$order['delivery_fee']);
            if ($amount < 1 || $amount > 999999999) {
                throw new DomainException('This order has no payable delivery fee.');
            }
            if ($order['status'] === 'draft') {
                $this->orderModel->set_status($orderId, 'pending_payment', 'Customer started PayMongo checkout.', 'customer', null);
            }
            $amountDecimal = intdiv($amount, 100) . '.' . str_pad((string)($amount % 100), 2, '0', STR_PAD_LEFT);
            $this->db->raw(
                "INSERT INTO payments (order_id, amount, fee, status, created_at, updated_at) VALUES (?, ?, 0.00, 'pending', ?, ?)",
                [$orderId, $amountDecimal, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
            );
            $paymentId = (int)$this->db->last_id();
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->roll_back();
            throw $e;
        }

        try {
            $session = $this->provider->createCheckoutSession($order, $amount, $successUrl, $cancelUrl);
            $url = $session['checkout_url'];
            $host = strtolower((string)parse_url($url, PHP_URL_HOST));
            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || $host !== 'checkout.paymongo.com') {
                throw new RuntimeException('PayMongo returned an invalid checkout URL.');
            }
            $this->db->raw(
                "UPDATE payments SET paymongo_session_id = ?, checkout_url = ?, updated_at = ? WHERE id = ? AND status = 'pending'",
                [$session['id'], $url, date('Y-m-d H:i:s'), $paymentId]
            );
            return ['checkout_url' => $url, 'reused' => false];
        } catch (Throwable $e) {
            // A provider timeout may have created a session. Keep the pending row so it cannot be blindly duplicated.
            $status = $e->getCode();
            if ($status >= 400 && $status < 500 && $status !== 429) {
                $this->db->raw("UPDATE payments SET status = 'failed', updated_at = ? WHERE id = ? AND status = 'pending'", [date('Y-m-d H:i:s'), $paymentId]);
            }
            throw $e;
        }
    }

    public static function toCentavos(string $amount): int
    {
        $amount = trim($amount);
        if (!preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('Invalid payment amount.');
        }
        return ((int)$matches[1] * 100) + (int)str_pad($matches[2] ?? '', 2, '0');
    }
}
