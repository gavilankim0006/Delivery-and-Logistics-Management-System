<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Add saved address']);
$formData = $form_data ?? [];
$errors = $errors ?? [];
$value = static fn(string $key): string => e($formData[$key] ?? '');
?>
<div class="container py-4 ds-page page-shell">
    <nav aria-label="Breadcrumb" class="breadcrumb-line mb-3"><a href="<?= base_url('addresses') ?>">Saved addresses</a><span aria-hidden="true"> / </span><span aria-current="page">Add address</span></nav>
    <header class="page-header"><div><h1 class="page-title">Add saved address</h1><p class="page-description">Save a pickup or drop-off location to your customer account.</p></div></header>
    <?php if (!empty($form_error)): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php endif; ?>
    <form class="address-form" method="post" action="<?= base_url('addresses') ?>" novalidate>
        <?= csrf_field() ?>
        <section class="surface surface-section">
            <?php foreach ([
                'label' => ['Label', 100, false],
                'address_line' => ['Address', 500, true],
                'contact_name' => ['Contact name', 100, true],
                'contact_phone' => ['Contact phone', 30, true]
            ] as $field => [$label, $maxLength, $required]): ?>
                <div class="mb-3"><label class="form-label" for="<?= e($field) ?>"><?= e($label) ?><?= !$required ? ' <span class="text-muted">(optional)</span>' : '' ?></label>
                    <input class="form-control <?= isset($errors[$field]) ? 'is-invalid' : '' ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= $field === 'contact_phone' ? 'tel' : 'text' ?>" maxlength="<?= (int)$maxLength ?>" value="<?= $value($field) ?>" <?= $required ? 'required' : '' ?>>
                    <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= e($errors[$field]) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="mb-0"><label class="form-label" for="notes">Notes <span class="text-muted">(optional)</span></label><textarea class="form-control <?= isset($errors['notes']) ? 'is-invalid' : '' ?>" id="notes" name="notes" rows="3" maxlength="1000"><?= $value('notes') ?></textarea><?php if (isset($errors['notes'])): ?><div class="invalid-feedback"><?= e($errors['notes']) ?></div><?php endif; ?></div>
        </section>
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-3"><a class="btn btn-outline-secondary" href="<?= base_url('addresses') ?>">Cancel</a><button class="btn btn-primary" type="submit">Save address</button></div>
    </form>
</div>
<?php $this->call->view('templates/footer'); ?>
