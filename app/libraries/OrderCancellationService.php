<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/CourierProviderFactory.php';

class OrderCancellationService
{
    private $orderModel;
    private ?CourierProviderInterface $driver;

    public function __construct($orderModel = null, ?CourierProviderInterface $driver = null)
    {
        $lava = function_exists('lava_instance') ? lava_instance() : null;
        if ($orderModel) {
            $this->orderModel = $orderModel;
        } elseif ($lava) {
            $this->orderModel = $lava->Order_model ?? (isset($lava->call) ? $lava->call->model('Order_model') : null);
        }
        $this->driver = $driver;
    }

    public function cancel(int $orderId, string $reason, string $actorType = 'customer', ?int $actorId = null): bool
    {
        $order = $this->orderModel->assert_cancellation_allowed($orderId);
        $providerOrderId = trim((string)($order['lalamove_order_id'] ?? ''));

        if ($providerOrderId !== '') {
            try {
                $driver = $this->driver ?? CourierProviderFactory::create();
                $response = $driver->cancelOrder($providerOrderId, $reason);
                if (isset($response['success']) && $response['success'] === false) {
                    throw new RuntimeException((string)($response['error'] ?? 'Provider rejected cancellation.'));
                }
            } catch (Throwable $e) {
                error_log(sprintf(
                    'Courier cancellation failed for order %d (provider order %s): %s',
                    $orderId,
                    $providerOrderId,
                    $e->getMessage()
                ));
                throw new RuntimeException(
                    'Courier cancellation could not be confirmed; the order remains active. Please try again or contact support.',
                    0,
                    $e
                );
            }
        }

        try {
            return $this->orderModel->cancel_order($orderId, $reason, $actorType, $actorId);
        } catch (Throwable $e) {
            if ($providerOrderId !== '') {
                $currentOrder = $this->orderModel->get_by_id($orderId);
                if ($currentOrder && ($currentOrder['status'] ?? '') === 'cancelled') {
                    return true;
                }
                error_log(sprintf(
                    'Courier cancelled but local cancellation failed for order %d (provider order %s): %s',
                    $orderId,
                    $providerOrderId,
                    $e->getMessage()
                ));
                throw new RuntimeException(
                    'The courier cancellation was accepted, but the local order could not be updated. Please contact support.',
                    0,
                    $e
                );
            }
            throw $e;
        }
    }
}
