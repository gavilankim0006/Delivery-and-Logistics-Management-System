<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Report_model extends Model
{
    protected function getDb()
    {
        return $this->db ?? lava_instance()->db;
    }

    public static function normalize_filters(string $type, array $input): array
    {
        $type = $type === 'payments' ? 'payments' : 'deliveries';
        $today = new DateTimeImmutable('today');
        $defaults = [
            'type' => $type,
            'from' => $today->modify('-29 days')->format('Y-m-d'),
            'to' => $today->format('Y-m-d'),
            'status' => '',
            'rider_id' => 0
        ];
        $filters = $defaults;
        $errors = [];

        foreach (['from', 'to'] as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                continue;
            }
            if (!is_scalar($input[$field])) {
                $errors[$field] = 'Enter a valid date.';
                continue;
            }
            $value = trim((string)$input[$field]);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $errors[$field] = 'Enter a valid date.';
                continue;
            }
            [$year, $month, $day] = array_map('intval', explode('-', $value));
            if (!checkdate($month, $day, $year)) {
                $errors[$field] = 'Enter a valid date.';
                continue;
            }
            $filters[$field] = $value;
        }

        if (!isset($errors['from']) && !isset($errors['to'])) {
            if ($filters['from'] > $filters['to']) {
                $errors['date_range'] = 'The start date must be on or before the end date.';
                $filters['from'] = $defaults['from'];
                $filters['to'] = $defaults['to'];
            } elseif ((new DateTimeImmutable($filters['from']))->diff(new DateTimeImmutable($filters['to']))->days > 366) {
                $errors['date_range'] = 'Choose a date range of 367 days or fewer.';
                $filters['from'] = $defaults['from'];
                $filters['to'] = $defaults['to'];
            }
        }

        $allowedStatuses = $type === 'payments'
            ? ['pending', 'paid', 'failed', 'refunded', 'cancelled']
            : ['draft', 'pending_payment', 'paid', 'booking', 'rider_assigned', 'picked_up', 'delivered', 'cancelled', 'failed'];
        $statusInput = $input['status'] ?? '';
        $status = is_scalar($statusInput) ? trim((string)$statusInput) : '';
        if (!is_scalar($statusInput)) {
            $errors['status'] = 'Choose a valid status.';
        } elseif ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $filters['status'] = $status;
        } elseif ($status !== '') {
            $errors['status'] = 'Choose a valid status.';
        }

        $riderInput = $input['rider_id'] ?? '';
        $riderId = is_scalar($riderInput) ? filter_var($riderInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($type === 'deliveries' && ($riderInput === '' || $riderInput === '0' || $riderInput === 0 || $riderInput === null)) {
            $filters['rider_id'] = 0;
        } elseif ($type === 'deliveries' && $riderId !== false) {
            $filters['rider_id'] = (int)$riderId;
        } elseif ($type === 'deliveries') {
            $errors['rider_id'] = 'Choose a valid rider.';
        }

        return ['filters' => $filters, 'errors' => $errors];
    }

    public function get_delivery_report(array $filters): array
    {
        [$where, $params] = $this->delivery_conditions($filters);
        $db = $this->getDb();
        $summary = $db->raw(
            "SELECT COUNT(*) AS order_count,
                    COALESCE(SUM(o.delivery_fee), 0) AS delivery_fees,
                    COALESCE(SUM(COALESCE(paid.paid_amount, 0)), 0) AS paid_amount
             FROM orders o
             INNER JOIN customers c ON c.id = o.customer_id
             INNER JOIN users u ON u.id = c.user_id
             LEFT JOIN (
                 SELECT order_id, SUM(amount) AS paid_amount
                 FROM payments WHERE status = 'paid' GROUP BY order_id
             ) paid ON paid.order_id = o.id
             {$where}",
            $params
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $rows = $db->raw(
            "SELECT o.id, o.order_no, u.name AS customer_name, r.name AS rider_name,
                    o.status, o.distance_km, o.delivery_fee,
                    COALESCE(paid.paid_amount, 0) AS paid_amount, o.created_at
             FROM orders o
             INNER JOIN customers c ON c.id = o.customer_id
             INNER JOIN users u ON u.id = c.user_id
             LEFT JOIN riders r ON r.id = o.rider_id
             LEFT JOIN (
                 SELECT order_id, SUM(amount) AS paid_amount
                 FROM payments WHERE status = 'paid' GROUP BY order_id
             ) paid ON paid.order_id = o.id
             {$where}
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT 500",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);

        return ['summary' => $summary, 'rows' => $rows];
    }

    /** Read-only time-series summary for the admin Analytics surface. */
    public function get_analytics_report(string $from, string $to): array
    {
        $db = $this->getDb();
        $start = $from . ' 00:00:00';
        $endExclusive = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
        $summary = $db->raw(
            "SELECT COUNT(*) AS order_count,
                    COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END), 0) AS delivered_count,
                    COALESCE(SUM(CASE WHEN o.status IN ('failed', 'cancelled') THEN 1 ELSE 0 END), 0) AS unsuccessful_count
             FROM orders o
             WHERE o.deleted_at IS NULL AND o.created_at >= ? AND o.created_at < ?",
            [$start, $endExclusive]
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $paymentSummary = $db->raw(
            "SELECT COUNT(*) AS payment_count,
                    COALESCE(SUM(CASE WHEN p.status = 'paid' AND p.paid_at >= ? AND p.paid_at < ? THEN p.amount ELSE 0 END), 0) AS paid_revenue,
                    COALESCE(SUM(CASE WHEN p.status = 'failed' THEN 1 ELSE 0 END), 0) AS failed_payment_count
             FROM payments p
             INNER JOIN orders o ON o.id = p.order_id
             WHERE o.deleted_at IS NULL AND p.created_at >= ? AND p.created_at < ?",
            [$start, $endExclusive, $start, $endExclusive]
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $dailyRows = $db->raw(
            "SELECT DATE(created_at) AS report_date, COUNT(*) AS order_count
             FROM orders
             WHERE deleted_at IS NULL AND created_at >= ? AND created_at < ?
             GROUP BY DATE(created_at)
             ORDER BY report_date ASC",
            [$start, $endExclusive]
        )->fetchAll(PDO::FETCH_ASSOC);
        $dailyMap = [];
        foreach ($dailyRows as $row) {
            $dailyMap[(string)$row['report_date']] = (int)$row['order_count'];
        }
        $daily = [];
        $cursor = new DateTimeImmutable($from);
        $last = new DateTimeImmutable($to);
        while ($cursor <= $last) {
            $date = $cursor->format('Y-m-d');
            $daily[] = ['date' => $date, 'orders' => $dailyMap[$date] ?? 0];
            $cursor = $cursor->modify('+1 day');
        }
        $outcomeRows = $db->raw(
            "SELECT status, COUNT(*) AS order_count
             FROM orders
             WHERE deleted_at IS NULL AND created_at >= ? AND created_at < ?
             GROUP BY status
             ORDER BY order_count DESC, status ASC",
            [$start, $endExclusive]
        )->fetchAll(PDO::FETCH_ASSOC);
        $statusLabels = [
            'draft' => 'Draft', 'pending_payment' => 'Awaiting payment', 'paid' => 'Paid',
            'booking' => 'Booking', 'rider_assigned' => 'Rider assigned', 'picked_up' => 'Picked up',
            'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'failed' => 'Failed'
        ];
        $total = (int)($summary['order_count'] ?? 0);
        $outcomes = array_map(static function (array $row) use ($statusLabels, $total): array {
            $count = (int)$row['order_count'];
            return [
                'status' => (string)$row['status'],
                'label' => $statusLabels[$row['status']] ?? ucfirst(str_replace('_', ' ', (string)$row['status'])),
                'count' => $count,
                'percent' => $total > 0 ? round($count * 100 / $total, 1) : 0
            ];
        }, $outcomeRows);
        return [
            'summary' => array_merge($summary, $paymentSummary),
            'daily' => $daily,
            'outcomes' => $outcomes
        ];
    }

    public function get_payment_report(array $filters): array
    {
        [$where, $params] = $this->payment_conditions($filters);
        $db = $this->getDb();
        $summary = $db->raw(
            "SELECT COUNT(*) AS payment_count,
                    COALESCE(SUM(p.amount), 0) AS payment_amount,
                    COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END), 0) AS paid_amount,
                    COALESCE(SUM(p.fee), 0) AS payment_fees
             FROM payments p
             INNER JOIN orders o ON o.id = p.order_id
             {$where}",
            $params
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $rows = $db->raw(
            "SELECT p.id, o.order_no, p.status, p.amount, p.fee,
                    p.payment_method, p.paid_at, p.created_at
             FROM payments p
             INNER JOIN orders o ON o.id = p.order_id
             {$where}
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT 500",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);

        return ['summary' => $summary, 'rows' => $rows];
    }

    public static function safe_csv_cell($value): string
    {
        $value = (string)$value;
        return preg_match('/^[\\s]*[=+@\\-]/u', $value) ? "'" . $value : $value;
    }

    private function delivery_conditions(array $filters): array
    {
        $where = 'WHERE o.deleted_at IS NULL AND o.created_at >= ? AND o.created_at < ?';
        $params = [$filters['from'] . ' 00:00:00', (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'];
        if ($filters['status'] !== '') {
            $where .= ' AND o.status = ?';
            $params[] = $filters['status'];
        }
        if ((int)$filters['rider_id'] > 0) {
            $where .= ' AND o.rider_id = ?';
            $params[] = (int)$filters['rider_id'];
        }
        return [$where, $params];
    }

    private function payment_conditions(array $filters): array
    {
        $where = 'WHERE o.deleted_at IS NULL AND p.created_at >= ? AND p.created_at < ?';
        $params = [$filters['from'] . ' 00:00:00', (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'];
        if ($filters['status'] !== '') {
            $where .= ' AND p.status = ?';
            $params[] = $filters['status'];
        }
        return [$where, $params];
    }
}
