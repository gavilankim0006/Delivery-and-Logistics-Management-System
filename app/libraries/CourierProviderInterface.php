<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

interface CourierProviderInterface
{
    public function getQuote(array $order): array;

    public function placeOrder(array $order, array $quote): array;

    public function cancelOrder(string $orderId, string $reason = ''): array;

    public function driverDetails(string $driverId, ?string $orderId = null): array;

    public function webhookEvent(string $eventType, array $order, array $extra = []): array;
}
