<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Analytics and Reports']);
$type = $filters['type'] ?? 'deliveries';
$summary = $report['summary'] ?? [];
$rows = $report['rows'] ?? [];
$statusOptions = $type === 'payments'
    ? ['pending', 'paid', 'failed', 'refunded', 'cancelled']
    : ['draft', 'pending_payment', 'paid', 'booking', 'rider_assigned', 'picked_up', 'delivered', 'cancelled', 'failed'];
$exportQuery = http_build_query(array_merge($filters, ['export' => 'csv']));
?>

<div class="container page-shell py-4">
    <header class="page-header mb-4">
        <div>
            <h1 class="page-title">Reports</h1>
            <p class="page-description">Read-only operational delivery and recorded payment summaries. Payment data reflects stored records, not live payment-provider reconciliation.</p>
        </div>
        <a class="btn btn-primary" href="<?= base_url('admin/reports?' . e($exportQuery)) ?>">Export CSV</a>
    </header>

    <?php if (!empty($report_error)): ?>
        <div class="alert alert-warning" role="alert"><?= e($report_error) ?></div>
    <?php endif; ?>

    <section class="surface surface-section mb-4" aria-label="Report filters">
        <form action="<?= base_url('admin/reports') ?>" method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label for="report-type" class="form-label">Report</label>
                <select id="report-type" name="type" class="form-select">
                    <option value="deliveries" <?= $type === 'deliveries' ? 'selected' : '' ?>>Deliveries</option>
                    <option value="payments" <?= $type === 'payments' ? 'selected' : '' ?>>Payments</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label for="report-from" class="form-label">From</label>
                <input id="report-from" class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>" required>
            </div>
            <div class="col-12 col-md-2">
                <label for="report-to" class="form-label">To</label>
                <input id="report-to" class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>" required>
            </div>
            <div class="col-12 col-md-2">
                <label for="report-status" class="form-label">Status</label>
                <select id="report-status" name="status" class="form-select">
                    <option value="">All statuses</option>
                    <?php foreach ($statusOptions as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($type === 'deliveries'): ?>
                <div class="col-12 col-md-2">
                    <label for="report-rider" class="form-label">Rider</label>
                    <select id="report-rider" name="rider_id" class="form-select">
                        <option value="0">All riders</option>
                        <?php foreach (($riders ?? []) as $rider): ?>
                            <option value="<?= (int)$rider['id'] ?>" <?= (int)$filters['rider_id'] === (int)$rider['id'] ? 'selected' : '' ?>><?= e($rider['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-1 d-grid"><button class="btn btn-secondary" type="submit">Apply</button></div>
        </form>
        <?php foreach (($errors ?? []) as $error): ?>
            <p class="text-danger mt-3 mb-0" role="alert"><?= e($error) ?></p>
        <?php endforeach; ?>
    </section>

    <section class="row g-3 mb-4" aria-label="Report summary">
        <?php if ($type === 'payments'): ?>
            <?php foreach ([
                'Payments' => number_format((int)($summary['payment_count'] ?? 0)),
                'Recorded amount' => '₱' . number_format((float)($summary['payment_amount'] ?? 0), 2),
                'Paid amount' => '₱' . number_format((float)($summary['paid_amount'] ?? 0), 2),
                'Payment fees' => '₱' . number_format((float)($summary['payment_fees'] ?? 0), 2)
            ] as $label => $value): ?>
                <div class="col-12 col-sm-6 col-xl-3"><article class="surface p-3 h-100"><div class="small text-muted"><?= e($label) ?></div><div class="h3 mb-0"><?= e($value) ?></div></article></div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ([
                'Orders' => number_format((int)($summary['order_count'] ?? 0)),
                'Delivery fees' => '₱' . number_format((float)($summary['delivery_fees'] ?? 0), 2),
                'Recorded paid amount' => '₱' . number_format((float)($summary['paid_amount'] ?? 0), 2)
            ] as $label => $value): ?>
                <div class="col-12 col-sm-6 col-xl-4"><article class="surface p-3 h-100"><div class="small text-muted"><?= e($label) ?></div><div class="h3 mb-0"><?= e($value) ?></div></article></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="surface" aria-label="Report results">
        <div class="d-flex flex-wrap justify-content-between align-items-center p-3 border-bottom gap-2">
            <h2 class="h5 mb-0"><?= $type === 'payments' ? 'Payment records' : 'Delivery orders' ?></h2>
            <span class="small text-muted">Showing up to 500 records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-clean align-middle mb-0">
                <thead><tr>
                    <th>Order</th>
                    <?php if ($type === 'payments'): ?>
                        <th>Status</th><th>Amount</th><th>Fee</th><th>Method</th><th>Paid at</th>
                    <?php else: ?>
                        <th>Customer</th><th>Rider</th><th>Status</th><th>Distance</th><th>Delivery fee</th><th>Paid amount</th>
                    <?php endif; ?>
                    <th>Created</th>
                </tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?= $type === 'payments' ? 7 : 8 ?>" class="text-center text-muted py-4">No records match these filters.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['order_no'] ?? '') ?></td>
                        <?php if ($type === 'payments'): ?>
                            <td><?= e(ucwords((string)($row['status'] ?? ''))) ?></td>
                            <td>₱<?= number_format((float)($row['amount'] ?? 0), 2) ?></td>
                            <td>₱<?= number_format((float)($row['fee'] ?? 0), 2) ?></td>
                            <td><?= e($row['payment_method'] ?? '—') ?></td>
                            <td><?= e($row['paid_at'] ?? '—') ?></td>
                        <?php else: ?>
                            <td><?= e($row['customer_name'] ?? '') ?></td>
                            <td><?= e($row['rider_name'] ?? 'Unassigned') ?></td>
                            <td><?= e(ucwords(str_replace('_', ' ', (string)($row['status'] ?? '')))) ?></td>
                            <td><?= number_format((float)($row['distance_km'] ?? 0), 2) ?> km</td>
                            <td>₱<?= number_format((float)($row['delivery_fee'] ?? 0), 2) ?></td>
                            <td>₱<?= number_format((float)($row['paid_amount'] ?? 0), 2) ?></td>
                        <?php endif; ?>
                        <td><?= e($row['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php $this->call->view('templates/footer'); ?>
