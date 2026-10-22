<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/CourierBookingService.php';

class DispatchRecoveryService
{
    public const DELAY_THRESHOLD_MINUTES = 60;

    public static function ageMinutes(?string $statusAt, ?string $now = null): ?int
    {
        if (!$statusAt) {
            return null;
        }
        $timestamp = strtotime($statusAt);
        $current = strtotime($now ?? 'now');
        if ($timestamp === false || $current === false || $timestamp > $current) {
            return null;
        }
        return (int)floor(($current - $timestamp) / 60);
    }

    public static function recommendation(array $order, ?string $now = null): ?array
    {
        $status = (string)($order['status'] ?? '');
        $age = self::ageMinutes($order['status_since'] ?? null, $now);
        $providerId = trim((string)($order['lalamove_order_id'] ?? ''));
        $quotationId = trim((string)($order['lalamove_quotation_id'] ?? ''));
        $recoveryLocked = !empty($order['recovery_locked']);
        if ($recoveryLocked) {
            return [
                'kind' => 'escalate', 'label' => 'Review courier booking', 'age_minutes' => $age,
                'priority' => 'failed', 'message' => 'A recovery attempt is in progress or provider state needs review; do not retry automatically.'
            ];
        }
        if ($age === null) {
            return null;
        }
        if ($status === 'paid' && $providerId === '' && $quotationId === '') {
            return [
                'kind' => 'book', 'label' => 'Book a courier', 'age_minutes' => $age,
                'priority' => $age >= self::DELAY_THRESHOLD_MINUTES ? 'delayed' : 'ready',
                'message' => $age >= self::DELAY_THRESHOLD_MINUTES ? 'Paid and unbooked for at least 60 minutes.' : 'Paid order has no courier booking.'
            ];
        }
        if ($status === 'failed' && $providerId === '' && $quotationId === '') {
            return [
                'kind' => 'retry', 'label' => 'Retry courier booking', 'age_minutes' => $age,
                'priority' => 'failed', 'message' => 'Booking failed before a provider quotation; no provider order is recorded.'
            ];
        }
        if ($status === 'failed' && ($providerId !== '' || $quotationId !== '')) {
            return [
                'kind' => 'escalate', 'label' => 'Check courier status', 'age_minutes' => $age,
                'priority' => 'failed', 'message' => 'Provider booking state is recorded or uncertain, so automatic retry is blocked.'
            ];
        }
        if (in_array($status, ['booking', 'rider_assigned'], true) && $age >= self::DELAY_THRESHOLD_MINUTES) {
            return [
                'kind' => 'escalate', 'label' => 'Review with customer', 'age_minutes' => $age,
                'priority' => 'delayed', 'message' => 'This order has remained in the current delivery stage for at least 60 minutes; provider state is not safe to retry automatically.'
            ];
        }
        return null;
    }

    public static function recover($db, $orderModel, int $orderId, string $actorType, ?int $actorId, ?CourierProviderInterface $driver = null): array
    {
        // CourierBookingService claims the unique recovery lock before any provider call.
        return (new CourierBookingService($db, $orderModel, $driver))->book($orderId, $actorType, $actorId);
    }
}
