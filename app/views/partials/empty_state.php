<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$emptyTitle = (string) ($title ?? 'Nothing here yet');
$emptyMessage = (string) ($message ?? 'When there is something to show, it will appear here.');
$emptyActionLabel = (string) ($action_label ?? '');
$emptyActionUrl = (string) ($action_url ?? '');
?>
<section class="ds-empty-state empty-state" aria-labelledby="empty-state-title">
    <svg class="ds-empty-state__route" viewBox="0 0 112 24" role="img" aria-label="A route connecting three stops" focusable="false">
        <path d="M12 12h88" fill="none" stroke="currentColor" stroke-width="2" />
        <circle cx="12" cy="12" r="5" fill="var(--surface)" stroke="currentColor" stroke-width="2" />
        <circle cx="56" cy="12" r="5" fill="var(--surface)" stroke="currentColor" stroke-width="2" />
        <circle cx="100" cy="12" r="5" fill="var(--surface)" stroke="currentColor" stroke-width="2" />
    </svg>
    <h2 class="ds-empty-state__title" id="empty-state-title"><?= e($emptyTitle) ?></h2>
    <p class="ds-empty-state__message"><?= e($emptyMessage) ?></p>
    <?php if ($emptyActionLabel !== '' && $emptyActionUrl !== ''): ?>
        <a class="btn btn-primary" href="<?= e($emptyActionUrl) ?>"><?= e($emptyActionLabel) ?></a>
    <?php endif; ?>
</section>
