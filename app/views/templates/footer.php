<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
?>
    </main>

    <footer class="app-footer mt-auto">
        <div class="container page-shell d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>&copy; <?= date('Y') ?> Dispatch</span>
            <span>Delivery and logistics management</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>">
        document.querySelectorAll('.dispatch-status-toggle').forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const panel = document.getElementById(toggle.getAttribute('aria-controls'));
                if (!panel) return;
                const expanded = toggle.getAttribute('aria-expanded') === 'true';
                const nextExpanded = !expanded;
                toggle.setAttribute('aria-expanded', String(nextExpanded));
                panel.hidden = !nextExpanded;
                if (nextExpanded) {
                    const select = panel.querySelector('select');
                    if (select) select.focus();
                } else {
                    toggle.focus();
                }
            });
        });

        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', (e) => {
                if (form.method.toLowerCase() !== 'post') return;
                if (e.defaultPrevented) return;
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;
                const button = form.querySelector('button[type="submit"]');
                if (!button || form.hasAttribute('data-no-disable') || button.disabled) return;
                setTimeout(() => {
                    if (e.defaultPrevented) return;
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                    button.dataset.originalLabel = button.textContent.trim();
                    const busyText = button.getAttribute('data-busy-text') || button.dataset.busyText || (() => {
                        const label = (button.dataset.originalLabel || '').toLowerCase();
                        if (label.includes('delete') || label.includes('remove')) return 'Deleting…';
                        if (label.includes('cancel')) return 'Cancelling…';
                        if (label.includes('sign in') || label.includes('log in')) return 'Signing in…';
                        if (label.includes('register') || label.includes('create account')) return 'Creating account…';
                        if (label.includes('send') || label.includes('dispatch')) return 'Sending…';
                        if (label.includes('pay')) return 'Redirecting…';
                        if (label.includes('save') || label.includes('update')) return 'Saving…';
                        return 'Processing…';
                    })();
                    button.textContent = busyText;
                }, 0);
            });
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="<?= base_url('public/js/ai-assistant.js') ?>"></script>
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
