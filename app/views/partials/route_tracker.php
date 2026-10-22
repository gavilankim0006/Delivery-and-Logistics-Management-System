<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Reusable order progress tracker. Expects $order with status, order_no and timeline.
 * Pass $compact = true for an order-list progress indicator.
 */
$trackerOrder = isset($order) && is_array($order) ? $order : [];
$trackerStatus = (string) ($trackerOrder['status'] ?? 'draft');
$trackerCompact = !empty($compact);
$trackerStages = [
    'placed' => 'Placed',
    'paid' => 'Paid',
    'rider_assigned' => 'Rider assigned',
    'picked_up' => 'Picked up',
    'delivered' => 'Delivered',
];
$trackerStageKeys = array_keys($trackerStages);
$trackerStatusStage = [
    'draft' => 'placed',
    'pending_payment' => 'placed',
    'paid' => 'paid',
    'booking' => 'paid',
    'rider_assigned' => 'rider_assigned',
    'picked_up' => 'picked_up',
    'delivered' => 'delivered',
];
$trackerTimelineStage = [
    'draft' => 'placed',
    'pending_payment' => 'placed',
    'paid' => 'paid',
    'booking' => 'paid',
    'rider_assigned' => 'rider_assigned',
    'picked_up' => 'picked_up',
    'delivered' => 'delivered',
];
$trackerMilestone = $trackerStatusStage[$trackerStatus] ?? null;
$trackerCurrent = $trackerMilestone === null ? false : array_search($trackerMilestone, $trackerStageKeys, true);
$trackerTerminal = in_array($trackerStatus, ['cancelled', 'failed'], true);
$trackerEnd = $trackerCurrent === false ? -1 : $trackerCurrent;
$trackerTimes = [];
foreach (($trackerOrder['timeline'] ?? []) as $trackerEntry) {
    $entryStatus = $trackerEntry['to_status'] ?? '';
    $milestone = $trackerTimelineStage[$entryStatus] ?? null;
    if ($milestone !== null) {
        $milestoneIndex = array_search($milestone, $trackerStageKeys, true);
        $trackerEnd = max($trackerEnd, $milestoneIndex);
        $trackerTimes[$milestone] = $trackerEntry['created_at'] ?? null;
    }
}
if (!$trackerTerminal && $trackerCurrent !== false) {
    $trackerEnd = max($trackerEnd, $trackerCurrent);
}
$trackerCurrent = $trackerTerminal || $trackerCurrent === false ? false : max($trackerCurrent, $trackerEnd);
$trackerProgress = max(0, min(4, $trackerEnd));
$trackerLabel = $trackerTerminal
    ? ucfirst($trackerStatus)
    : ($trackerCurrent === false ? 'In progress' : $trackerStages[$trackerStageKeys[$trackerCurrent]]);
$trackerId = 'route-tracker-' . (int) ($trackerOrder['id'] ?? 0);
?>
<div class="ds-route-tracker<?= $trackerCompact ? ' ds-route-tracker--compact' : '' ?> ds-route-tracker--progress-<?= (int) $trackerProgress ?>" role="group" aria-label="Delivery progress for <?= e($trackerOrder['order_no'] ?? 'this order') ?>">
    <p class="visually-hidden">Current status: <?= e($trackerLabel) ?>.</p>
    <ol class="ds-route-tracker__stages">
        <?php foreach ($trackerStages as $stageKey => $stageLabel): ?>
            <?php
            $stageIndex = array_search($stageKey, $trackerStageKeys, true);
            $reached = $stageIndex <= $trackerEnd;
            $current = !$trackerTerminal && $trackerCurrent !== false && $stageIndex === $trackerCurrent;
            $outcome = $trackerTerminal && $stageIndex === $trackerEnd;
            $stageClass = $reached ? ' is-complete' : '';
            $stageClass .= $current ? ' is-current' : '';
            $stageClass .= $outcome ? ' is-outcome' : '';
            $time = $trackerTimes[$stageKey] ?? null;
            ?>
            <li class="ds-route-tracker__stage<?= $stageClass ?>" id="<?= e($trackerId . '-' . $stageKey) ?>">
                <span class="ds-route-tracker__node" aria-hidden="true"><?= $outcome ? '×' : ($reached ? '✓' : '') ?></span>
                <span class="ds-route-tracker__label"><?= e($stageLabel) ?></span>
                <?php if ($time): ?>
                    <time class="ds-route-tracker__time" datetime="<?= e(date(DATE_ATOM, strtotime($time))) ?>"><?= e(date('M j, g:i a', strtotime($time))) ?></time>
                <?php else: ?>
                    <span class="ds-route-tracker__time"><?= $reached ? 'Reached' : 'Pending' ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
    <?php if ($trackerTerminal): ?>
        <p class="ds-route-tracker__outcome" role="status">This delivery was <?= e($trackerStatus) ?><?= !empty($trackerOrder['cancel_reason']) ? ': ' . e($trackerOrder['cancel_reason']) : '.' ?></p>
    <?php endif; ?>
</div>
