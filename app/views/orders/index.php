<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Orders']);
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$orderListProps = [
    'title' => $page_title ?? 'Orders',
    'orders' => $orders ?? [],
    'filters' => array_intersect_key($filters ?? [], array_flip(['status', 'date', 'search'])),
    'role' => $role ?? 'customer',
    'page' => (int)($page ?? 1),
    'totalPages' => (int)($total_pages ?? 1),
    'totalOrders' => (int)($total_orders ?? 0),
    'today' => date('Y-m-d'),
    'baseUrl' => base_url(),
    'flashes' => $flashes,
];
?>
<div
    id="vue-order-list-root"
    data-page="list"
    data-requires-vue="true"
    data-props="<?= e(json_encode($orderListProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to view orders.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/orders.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
