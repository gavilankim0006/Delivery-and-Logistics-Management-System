<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$statusLabels = [
    'draft' => 'Draft',
    'pending_payment' => 'Pending payment',
    'paid' => 'Paid',
    'booking' => 'Booking',
    'rider_assigned' => 'Rider assigned',
    'picked_up' => 'Picked up',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
    'failed' => 'Failed'
];
$statusClasses = [
    'draft' => 'draft',
    'pending_payment' => 'pending',
    'paid' => 'paid',
    'booking' => 'booking',
    'rider_assigned' => 'assigned',
    'picked_up' => 'picked',
    'delivered' => 'delivered',
    'cancelled' => 'cancelled',
    'failed' => 'failed'
];
$trackerProgress = [
    'draft' => 0,
    'pending_payment' => 0,
    'paid' => 1,
    'booking' => 1,
    'rider_assigned' => 2,
    'picked_up' => 3,
    'delivered' => 4
];
$stages = [
    ['label' => 'Placed', 'status' => 'draft'],
    ['label' => 'Paid', 'status' => 'paid'],
    ['label' => 'Rider assigned', 'status' => 'rider_assigned'],
    ['label' => 'Picked up', 'status' => 'picked_up'],
    ['label' => 'Delivered', 'status' => 'delivered']
];

$status = (string)($order['status'] ?? 'draft');
$timeline = $order['timeline'] ?? [];
$lastReached = $trackerProgress[$status] ?? 0;
$trackerTimes = [];
foreach ($timeline as $entry) {
    $stage = $trackerProgress[$entry['to_status']] ?? null;
    if ($stage !== null) {
        $trackerTimes[$stage] = $entry['created_at'];
        $lastReached = max($lastReached, $stage);
    }
}
$currentProgress = $trackerProgress[$status] ?? -1;
$isOutcome = in_array($status, ['cancelled', 'failed'], true);

$etaText = '';
if ($status === 'picked_up' && !empty($order['eta_at'])) {
    $etaText = 'Estimated arrival around ' . date('g:i A', strtotime((string)$order['eta_at'])) . '.';
} elseif ($status === 'rider_assigned') {
    $etaText = 'A rider is on the way to pick up your package.';
} elseif ($status === 'delivered') {
    $etaText = 'This delivery has been completed.';
} elseif ($status === 'cancelled') {
    $etaText = 'This delivery was cancelled.';
} elseif ($status === 'failed') {
    $etaText = 'There was an issue with this delivery. Please contact the sender.';
} elseif (in_array($status, ['paid', 'booking'], true)) {
    $etaText = 'We are arranging a rider for this delivery.';
}

$formatTime = static function (?string $value): string {
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('M j, g:i A', $ts) : '';
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title><?= e($page_title ?? 'Track delivery') ?> · Dispatch</title>
    <?php $appCssPath = dirname(__DIR__, 3) . '/public/css/app.css'; ?>
    <link href="<?= e(base_url('css/app.css')) ?><?= is_file($appCssPath) ? '?v=' . (string)filemtime($appCssPath) : '' ?>" rel="stylesheet">
    <style>
        body.tracking-page {
            margin: 0;
            padding: 32px 16px 64px;
            background: var(--paper, #EEF2F5);
            color: var(--ink, #172B3A);
            font-family: 'Manrope', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        .tracking-shell { max-width: 680px; margin: 0 auto; }
        .tracking-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 800;
            color: var(--ink, #172B3A);
            text-decoration: none;
            margin-bottom: 16px;
        }
        .tracking-brand span { color: var(--route, #087F98); }
        .tracking-card {
            background: var(--surface, #fff);
            border: 1px solid var(--line, #D3DDE5);
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(20, 33, 61, 0.08);
            margin-bottom: 16px;
        }
        .tracking-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; }
        .tracking-order-no { font-size: 20px; font-weight: 800; margin: 0; letter-spacing: -0.01em; }
        .tracking-eta {
            margin: 0 0 20px;
            padding: 12px 16px;
            border-radius: 10px;
            background: color-mix(in srgb, var(--route, #087F98) 9%, transparent);
            color: var(--route-dark, #06657a);
            font-weight: 600;
        }
        .tracking-route-stop { display: grid; grid-template-columns: 20px 1fr; gap: 10px; margin-bottom: 12px; }
        .tracking-dot { width: 11px; height: 11px; border-radius: 50%; margin-top: 5px; }
        .tracking-dot--pickup { background: var(--success, #146443); }
        .tracking-dot--dropoff { background: var(--route, #087F98); }
        .tracking-route-stop strong {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--ink-3, #617687);
        }
        .tracking-meta { font-size: 13px; color: var(--ink-2, #4C6070); margin: 0; }
        .tracking-timeline { list-style: none; margin: 0; padding: 0; }
        .tracking-timeline li {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 8px;
            padding: 10px 0;
            border-bottom: 1px solid var(--paper, #EEF2F5);
            font-size: 14px;
        }
        .tracking-timeline li:last-child { border-bottom: 0; }
        .tracking-updated { font-size: 13px; color: var(--ink-3, #617687); text-align: center; }
        .tracking-note { font-size: 12px; color: var(--ink-3, #617687); text-align: center; margin-top: 8px; }
        @media (max-width: 575.98px) {
            .tracking-card { padding: 18px; }
        }
    </style>
</head>
<body class="tracking-page" data-tracking-endpoint="<?= e(base_url('track/' . rawurlencode((string)$token) . '/data')) ?>">
    <div class="tracking-shell">
        <a class="tracking-brand" href="<?= e(base_url()) ?>">Dispatch <span>·</span> Delivery &amp; Logistics</a>

        <div class="tracking-card">
            <header class="tracking-header">
                <h1 class="tracking-order-no" id="track-order-no"><?= e((string)$order['order_no']) ?></h1>
                <span
                    class="status-chip ds-status-chip status-chip--<?= e($statusClasses[$status] ?? 'draft') ?>"
                    id="track-status-chip"
                    data-status="<?= e($status) ?>"
                >
                    <span aria-hidden="true">●</span> <span id="track-status-label"><?= e($statusLabels[$status] ?? 'Unknown') ?></span>
                </span>
            </header>

            <p class="tracking-eta" id="track-eta" <?= $etaText === '' ? 'hidden' : '' ?>><?= e($etaText) ?></p>

            <section aria-label="Delivery route" class="mb-4">
                <div class="tracking-route-stop">
                    <span class="tracking-dot tracking-dot--pickup" aria-hidden="true"></span>
                    <div>
                        <strong>Pickup</strong>
                        <?= e((string)($order['pickup_address'] ?? '')) ?>
                    </div>
                </div>
                <div class="tracking-route-stop">
                    <span class="tracking-dot tracking-dot--dropoff" aria-hidden="true"></span>
                    <div>
                        <strong>Drop-off</strong>
                        <?= e((string)($order['dropoff_address'] ?? '')) ?>
                    </div>
                </div>
                <p class="tracking-meta">
                    <?php if (!empty($order['parcel_description'])): ?><?= e((string)$order['parcel_description']) ?><?php endif; ?>
                    <?php if (!empty($order['distance_km'])): ?> · <?= e(number_format((float)$order['distance_km'], 2)) ?> km<?php endif; ?>
                    <?php if (!empty($order['rider_name'])): ?> · Rider: <?= e((string)$order['rider_name']) ?><?php endif; ?>
                </p>
            </section>

            <section aria-labelledby="track-progress-heading">
                <h2 class="h6 mb-3" id="track-progress-heading">Delivery progress</h2>
                <div
                    class="ds-route-tracker ds-route-tracker--progress-<?= (int)max(0, min(4, $lastReached)) ?>"
                    id="track-tracker"
                    role="group"
                    aria-label="Delivery progress"
                >
                    <ol class="ds-route-tracker__stages">
                        <?php foreach ($stages as $index => $stage): ?>
                        <li
                            class="ds-route-tracker__stage<?= $index <= $lastReached ? ' is-complete' : '' ?><?= !$isOutcome && $index === $currentProgress ? ' is-current' : '' ?><?= $isOutcome && $index === $lastReached ? ' is-outcome' : '' ?>"
                            data-stage-index="<?= (int)$index ?>"
                        >
                            <span class="ds-route-tracker__node" aria-hidden="true"><?= $isOutcome && $index === $lastReached ? '×' : ($index <= $lastReached ? '✓' : '') ?></span>
                            <span class="ds-route-tracker__label"><?= e($stage['label']) ?></span>
                            <time class="ds-route-tracker__time"><?= e(isset($trackerTimes[$index]) ? $formatTime($trackerTimes[$index]) : ($index <= $lastReached ? 'Reached' : 'Pending')) ?></time>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </section>
        </div>

        <div class="tracking-card">
            <h2 class="h6 mb-3">Latest updates</h2>
            <ul class="tracking-timeline" id="track-timeline">
                <?php foreach ($timeline as $entry): ?>
                <li>
                    <strong><?= e($statusLabels[$entry['to_status']] ?? (string)$entry['to_status']) ?></strong>
                    <span class="text-muted"><?= e($formatTime((string)$entry['created_at'])) ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (empty($timeline)): ?>
                <li><span class="text-muted">No delivery updates yet.</span></li>
                <?php endif; ?>
            </ul>
        </div>

        <p class="tracking-updated" id="track-updated">Updated <?= e($formatTime((string)$order['updated_at'])) ?></p>
        <p class="tracking-note">This page refreshes automatically. It was shared by the sender of this delivery.</p>
    </div>

    <script nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>">
        (function () {
            var endpoint = document.body.getAttribute('data-tracking-endpoint');
            if (!endpoint) return;

            var statusLabels = {
                draft: 'Draft', pending_payment: 'Pending payment', paid: 'Paid', booking: 'Booking',
                rider_assigned: 'Rider assigned', picked_up: 'Picked up', delivered: 'Delivered',
                cancelled: 'Cancelled', failed: 'Failed'
            };
            var statusClasses = {
                draft: 'draft', pending_payment: 'pending', paid: 'paid', booking: 'booking',
                rider_assigned: 'assigned', picked_up: 'picked', delivered: 'delivered',
                cancelled: 'cancelled', failed: 'failed'
            };
            var progress = {
                draft: 0, pending_payment: 0, paid: 1, booking: 1,
                rider_assigned: 2, picked_up: 3, delivered: 4
            };

            function formatTime(value) {
                if (!value) return '';
                var parsed = new Date(String(value).replace(' ', 'T'));
                return isNaN(parsed.getTime()) ? String(value)
                    : parsed.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
            }

            function etaText(order) {
                if (order.status === 'picked_up' && order.eta_at) {
                    return 'Estimated arrival around ' + new Date(String(order.eta_at).replace(' ', 'T'))
                        .toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }) + '.';
                }
                if (order.status === 'rider_assigned') return 'A rider is on the way to pick up your package.';
                if (order.status === 'delivered') return 'This delivery has been completed.';
                if (order.status === 'cancelled') return 'This delivery was cancelled.';
                if (order.status === 'failed') return 'There was an issue with this delivery. Please contact the sender.';
                if (order.status === 'paid' || order.status === 'booking') return 'We are arranging a rider for this delivery.';
                return '';
            }

            function render(payload) {
                var order = payload.order || {};
                var timeline = payload.timeline || [];

                var chip = document.getElementById('track-status-chip');
                var label = document.getElementById('track-status-label');
                if (chip && label) {
                    chip.className = 'status-chip ds-status-chip status-chip--' + (statusClasses[order.status] || 'draft');
                    chip.setAttribute('data-status', order.status || '');
                    label.textContent = statusLabels[order.status] || order.status || 'Unknown';
                }

                var lastReached = progress[order.status] || 0;
                var stageTimes = {};
                timeline.forEach(function (entry) {
                    var stage = progress[entry.to_status];
                    if (stage !== undefined) {
                        stageTimes[stage] = entry.created_at;
                        lastReached = Math.max(lastReached, stage);
                    }
                });
                var isOutcome = order.status === 'cancelled' || order.status === 'failed';
                var current = progress[order.status];
                if (current === undefined) current = -1;

                var stages = document.querySelectorAll('#track-tracker .ds-route-tracker__stage');
                stages.forEach(function (stageEl) {
                    var index = Number(stageEl.getAttribute('data-stage-index'));
                    stageEl.classList.toggle('is-complete', index <= lastReached);
                    stageEl.classList.toggle('is-current', !isOutcome && index === current);
                    stageEl.classList.toggle('is-outcome', isOutcome && index === lastReached);
                    var node = stageEl.querySelector('.ds-route-tracker__node');
                    if (node) node.textContent = (isOutcome && index === lastReached) ? '×' : (index <= lastReached ? '✓' : '');
                    var time = stageEl.querySelector('.ds-route-tracker__time');
                    if (time) time.textContent = stageTimes[index] ? formatTime(stageTimes[index]) : (index <= lastReached ? 'Reached' : 'Pending');
                });
                var tracker = document.getElementById('track-tracker');
                if (tracker) tracker.className = 'ds-route-tracker ds-route-tracker--progress-' + Math.max(0, Math.min(4, lastReached));

                var eta = document.getElementById('track-eta');
                if (eta) {
                    var text = etaText(order);
                    eta.textContent = text;
                    if (text) { eta.removeAttribute('hidden'); } else { eta.setAttribute('hidden', 'hidden'); }
                }

                var list = document.getElementById('track-timeline');
                if (list) {
                    list.textContent = '';
                    if (!timeline.length) {
                        var empty = document.createElement('li');
                        var span = document.createElement('span');
                        span.className = 'text-muted';
                        span.textContent = 'No delivery updates yet.';
                        empty.appendChild(span);
                        list.appendChild(empty);
                    } else {
                        timeline.forEach(function (entry) {
                            var item = document.createElement('li');
                            var name = document.createElement('strong');
                            name.textContent = statusLabels[entry.to_status] || entry.to_status;
                            var time = document.createElement('span');
                            time.className = 'text-muted';
                            time.textContent = formatTime(entry.created_at);
                            item.appendChild(name);
                            item.appendChild(time);
                            list.appendChild(item);
                        });
                    }
                }

                var updated = document.getElementById('track-updated');
                if (updated) updated.textContent = 'Updated ' + formatTime(order.updated_at);
            }

            var timer = null;
            function schedule(delay) {
                window.clearTimeout(timer);
                timer = window.setTimeout(poll, delay);
            }
            function poll() {
                fetch(endpoint, { credentials: 'omit', headers: { Accept: 'application/json' }, cache: 'no-store' })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (payload) {
                        if (payload && payload.success && payload.data) render(payload.data);
                    })
                    .catch(function () { /* next interval retries */ })
                    .finally(function () { schedule(20000); });
            }

            schedule(20000);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    window.clearTimeout(timer);
                    poll();
                }
            });
        })();
    </script>
</body>
</html>
