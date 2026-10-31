<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Delivery details']);
$orderId = (int)$order['order_id'];
$riderProps = [
    'order' => $order,
    'proofRecorded' => !empty($proof_recorded),
    'baseUrl' => base_url(),
    'csrfField' => csrf_field(),
    'csrfToken' => csrf_token(),
    'locationUrl' => base_url('rider/orders/' . $orderId . '/location')
];
?>
<div id="vue-rider-root" data-page="show" data-props="<?= e(json_encode($riderProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <noscript><div class="container py-4"><div class="alert alert-warning">Enable JavaScript to view delivery details.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('public/build/rider.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
