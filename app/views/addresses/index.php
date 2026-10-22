<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Saved addresses']);
?>
<div class="container py-4 ds-page page-shell">
    <header class="page-header ds-page-header">
        <div>
            <h1 class="h3 fw-bold mb-1 page-title">Saved addresses</h1>
            <p class="text-muted mb-0 page-description">Pickup and drop-off locations saved to your customer account.</p>
        </div>
        <a class="btn btn-primary" href="<?= base_url('addresses/new') ?>">Add address</a>
    </header>

    <?php if (!empty($_SESSION['flash']['success']) || !empty($_SESSION['flash']['error'])): ?>
        <div class="alert <?= !empty($_SESSION['flash']['error']) ? 'alert-danger' : 'alert-success' ?>" role="<?= !empty($_SESSION['flash']['error']) ? 'alert' : 'status' ?>"><?= e($_SESSION['flash']['error'] ?? $_SESSION['flash']['success']) ?></div>
        <?php unset($_SESSION['flash']['success'], $_SESSION['flash']['error']); ?>
    <?php endif; ?>

    <?php if (empty($addresses)): ?>
        <?php $this->call->view('partials/empty_state', [
            'title' => 'No saved addresses yet',
            'message' => 'Save pickup and drop-off details once, then reuse them when you create an order.',
            'action_label' => 'Add address',
            'action_url' => base_url('addresses/new')
        ]); ?>
    <?php else: ?>
        <div class="surface ds-panel">
            <?php foreach ($addresses as $index => $address): ?>
                <article class="p-3 p-md-4 <?= $index > 0 ? 'border-top' : '' ?>">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <h2 class="h5 mb-2"><?= e($address['label'] ?: 'Address') ?></h2>
                        <div class="address-actions">
                            <a class="btn btn-outline-secondary" href="<?= base_url('addresses/' . (int)$address['id'] . '/edit') ?>">Edit</a>
                            <button class="btn btn-link text-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteAddressModal<?= (int)$address['id'] ?>">Delete</button>
                            <div class="modal fade" id="deleteAddressModal<?= (int)$address['id'] ?>" tabindex="-1" aria-labelledby="deleteAddressModalLabel<?= (int)$address['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h2 class="modal-title h5" id="deleteAddressModalLabel<?= (int)$address['id'] ?>">Delete saved address?</h2>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="mb-0">Delete <?= e($address['label'] ?: 'this address') ?> from your saved addresses? Addresses used by an order cannot be deleted.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep address</button>
                                            <form method="post" action="<?= base_url('addresses/' . (int)$address['id'] . '/delete') ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-danger" type="submit">Delete address</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="mb-3"><?= nl2br(e($address['address_line'])) ?></p>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Contact</dt>
                        <dd class="col-sm-9"><?= e($address['contact_name']) ?></dd>
                        <dt class="col-sm-3">Phone</dt>
                        <dd class="col-sm-9 mb-0"><?= e($address['contact_phone']) ?></dd>
                    </dl>
                    <?php if (!empty($address['notes'])): ?>
                        <p class="small text-muted mt-3 mb-0"><?= nl2br(e($address['notes'])) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php $this->call->view('templates/footer'); ?>
