<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$statusValue = (string) ($status ?? 'draft');
$statusLabels = [
    'draft' => 'Draft',
    'pending_payment' => 'Pending payment',
    'paid' => 'Paid',
    'booking' => 'Booking',
    'rider_assigned' => 'Rider assigned',
    'picked_up' => 'Picked up',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
    'failed' => 'Failed',
];
$statusLabel = (string) ($label ?? $statusLabels[$statusValue] ?? 'Unknown');
$statusClass = preg_replace('/[^a-z0-9_-]/i', '-', strtolower($statusValue));
?>
<span class="ds-status-chip ds-status-chip--<?= e($statusClass) ?> status-chip status-chip--<?= e($statusClass) ?>" role="status">
    <span class="visually-hidden">Status: </span><?= e($statusLabel) ?>
</span>
