<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Delivery link unavailable']);
?>
<div class="container page-shell py-5"><div class="surface p-4"><h1 class="h3">Delivery link unavailable</h1><p class="mb-0">This delivery control link is invalid, expired, or has been revoked.</p></div></div>
<?php $this->call->view('templates/footer'); ?>
