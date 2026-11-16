<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$orderNo = (string)($order['order_no'] ?? '');
$paidAmount = number_format((float)($payment['amount'] ?? 0), 2);
$deliveryFee = number_format((float)($order['delivery_fee'] ?? 0), 2);
$paidAt = !empty($payment['paid_at']) ? date('M j, Y g:i A', strtotime((string)$payment['paid_at'])) : '';
$issuedAt = date('M j, Y g:i A');
$methodRaw = strtolower(trim((string)($payment['payment_method'] ?? '')));
$methodLabels = [
    'demo_simulated' => 'Demo payment (simulated)',
    'paymongo' => 'PayMongo (card / e-wallet)',
];
$methodLabel = $methodLabels[$methodRaw] ?? ($methodRaw !== '' ? ucwords(str_replace(['_', '-'], ' ', $methodRaw)) : 'Online payment');
$reference = trim((string)($payment['paymongo_session_id'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>Receipt · <?= e($orderNo) ?> · Dispatch</title>
    <?php $appCssPath = dirname(__DIR__, 3) . '/public/css/app.css'; ?>
    <link href="<?= e(base_url('css/app.css')) ?><?= is_file($appCssPath) ? '?v=' . (string)filemtime($appCssPath) : '' ?>" rel="stylesheet">
    <style>
        body.receipt-page {
            margin: 0;
            padding: 32px 16px 64px;
            background: var(--paper, #EEF2F5);
            color: var(--ink, #172B3A);
            font-family: 'Manrope', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        .receipt-shell { max-width: 720px; margin: 0 auto; }
        .receipt-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .receipt-actions .receipt-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--line, #D3DDE5);
            background: var(--surface, #fff);
            color: var(--ink, #172B3A);
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }
        .receipt-actions .receipt-btn--primary {
            background: var(--route, #087F98);
            border-color: var(--route, #087F98);
            color: #fff;
        }
        .receipt-card {
            background: var(--surface, #fff);
            border: 1px solid var(--line, #D3DDE5);
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 8px 24px rgba(20, 33, 61, 0.08);
        }
        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--paper, #EEF2F5);
            margin-bottom: 20px;
        }
        .receipt-brand { font-size: 20px; font-weight: 800; letter-spacing: -0.01em; }
        .receipt-brand span { color: var(--route, #087F98); }
        .receipt-subtitle { margin: 4px 0 0; color: var(--ink-2, #4C6070); font-size: 13px; }
        .receipt-meta { text-align: right; font-size: 13px; color: var(--ink-2, #4C6070); }
        .receipt-meta strong { color: var(--ink, #172B3A); display: block; font-size: 15px; }
        .receipt-section { margin-bottom: 20px; }
        .receipt-section h2 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--ink-3, #617687);
            margin: 0 0 8px;
        }
        .receipt-route-stop { display: grid; grid-template-columns: 18px 1fr; gap: 10px; margin-bottom: 10px; }
        .receipt-dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 5px; }
        .receipt-dot--pickup { background: var(--success, #146443); }
        .receipt-dot--dropoff { background: var(--route, #087F98); }
        .receipt-route-stop strong { display: block; font-size: 12px; color: var(--ink-2, #4C6070); text-transform: uppercase; letter-spacing: 0.04em; }
        .receipt-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .receipt-table th, .receipt-table td { padding: 10px 0; border-bottom: 1px solid var(--paper, #EEF2F5); text-align: left; vertical-align: top; }
        .receipt-table th { font-weight: 600; color: var(--ink-2, #4C6070); }
        .receipt-table td.amount, .receipt-table th.amount { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .receipt-table tr.receipt-total td { font-weight: 800; font-size: 16px; border-bottom: 0; padding-top: 14px; }
        .receipt-note { margin-top: 20px; padding-top: 14px; border-top: 1px dashed var(--line, #D3DDE5); font-size: 12px; color: var(--ink-3, #617687); }
        @media (max-width: 575.98px) {
            .receipt-card { padding: 18px; }
            .receipt-header { flex-direction: column; }
            .receipt-meta { text-align: left; }
        }
        @media print {
            body.receipt-page { background: #fff; padding: 0; }
            .receipt-actions { display: none; }
            .receipt-card { border: 0; box-shadow: none; border-radius: 0; padding: 0; }
        }
    </style>
</head>
<body class="receipt-page">
    <div class="receipt-shell">
        <div class="receipt-actions">
            <a class="receipt-btn" href="<?= e(base_url('orders/' . (int)$order['id'])) ?>">&larr; Back to order</a>
            <button class="receipt-btn receipt-btn--primary" type="button" id="receipt-print">Print receipt</button>
        </div>

        <article class="receipt-card" aria-label="Payment receipt for order <?= e($orderNo) ?>">
            <header class="receipt-header">
                <div>
                    <div class="receipt-brand">Dispatch <span>·</span> Delivery &amp; Logistics</div>
                    <p class="receipt-subtitle">System-generated payment receipt</p>
                </div>
                <div class="receipt-meta">
                    <strong>Receipt <?= e($orderNo) ?></strong>
                    <span>Issued <?= e($issuedAt) ?></span>
                </div>
            </header>

            <section class="receipt-section" aria-label="Billed to">
                <h2>Billed to</h2>
                <p class="mb-0">
                    <strong><?= e((string)($order['customer_name'] ?? 'Valued customer')) ?></strong><br>
                    <?php if (!empty($order['company_name'])): ?><?= e((string)$order['company_name']) ?><br><?php endif; ?>
                    <?php if (!empty($order['customer_email'])): ?><?= e((string)$order['customer_email']) ?><?php endif; ?>
                </p>
            </section>

            <section class="receipt-section" aria-label="Delivery route">
                <h2>Delivery route</h2>
                <div class="receipt-route-stop">
                    <span class="receipt-dot receipt-dot--pickup" aria-hidden="true"></span>
                    <div>
                        <strong>Pickup</strong>
                        <?= e((string)($order['pickup_address'] ?? '')) ?>
                    </div>
                </div>
                <div class="receipt-route-stop">
                    <span class="receipt-dot receipt-dot--dropoff" aria-hidden="true"></span>
                    <div>
                        <strong>Drop-off</strong>
                        <?= e((string)($order['dropoff_address'] ?? '')) ?>
                    </div>
                </div>
                <p class="mb-0 small text-muted">
                    <?= e((string)($order['parcel_description'] ?? 'Package')) ?>
                    · <?= e((string)($order['weight_kg'] ?? '')) ?> kg
                    · <?= e(strtoupper((string)($order['parcel_size'] ?? ''))) ?>
                    <?= !empty($order['is_fragile']) ? ' · Fragile' : '' ?>
                    <?php if (!empty($order['distance_km'])): ?> · <?= e(number_format((float)$order['distance_km'], 2)) ?> km<?php endif; ?>
                </p>
            </section>

            <section class="receipt-section" aria-label="Payment summary">
                <h2>Payment summary</h2>
                <table class="receipt-table">
                    <tbody>
                        <tr>
                            <th scope="row">Delivery fee at booking</th>
                            <td class="amount">&#8369;<?= e($deliveryFee) ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Payment method</th>
                            <td class="amount"><?= e($methodLabel) ?></td>
                        </tr>
                        <?php if ($reference !== ''): ?>
                        <tr>
                            <th scope="row">Payment reference</th>
                            <td class="amount"><?= e($reference) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($paidAt !== ''): ?>
                        <tr>
                            <th scope="row">Paid on</th>
                            <td class="amount"><?= e($paidAt) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="receipt-total">
                            <td>Total paid</td>
                            <td class="amount">&#8369;<?= e($paidAmount) ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <p class="receipt-note mb-0">
                This receipt confirms that payment for order <?= e($orderNo) ?> has been recorded in the Dispatch system.
                Keep it for your records. For questions about this payment, contact Dispatch support and reference the receipt number above.
            </p>
        </article>
    </div>
    <script nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>">
        (function () {
            var button = document.getElementById('receipt-print');
            if (button) {
                button.addEventListener('click', function () { window.print(); });
            }
        })();
    </script>
</body>
</html>
