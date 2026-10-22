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
            form.addEventListener('submit', () => {
                if (form.method.toLowerCase() !== 'post') return;
                const button = form.querySelector('button[type="submit"]');
                if (!button || form.hasAttribute('data-no-disable') || button.disabled) return;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.dataset.originalLabel = button.textContent.trim();
                button.textContent = 'Saving...';
            });
        });
    </script>
    <?php if (isset($extra_js)): ?>
        <?= $extra_js ?>
    <?php endif; ?>
</body>
</html>
