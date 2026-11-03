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

    <?php if (auth_role() === 'customer'):
        $currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
        $navIsActive = static function (string $destination) use ($currentUri): bool {
            $path = rtrim($currentUri, '/') ?: '/';
            $target = '/' . trim($destination, '/');
            return $path === $target || str_ends_with($path, $target) || str_contains($path, $target . '/');
        };
        $isNewOrderActive = $navIsActive('orders/new');
        $isOrdersActive = ($navIsActive('orders') && !$isNewOrderActive) || $currentUri === '/' || str_ends_with($currentUri, '/orders');
        $isMessagesActive = $navIsActive('package-workflow');
        $isAddressesActive = $navIsActive('addresses');
        $isProfileActive = $navIsActive('profile');
    ?>
        <nav class="customer-bottom-nav" aria-label="Customer mobile navigation">
            <a href="<?= base_url('orders') ?>" class="customer-bottom-nav__item<?= $isOrdersActive ? ' is-active' : '' ?>"<?= $isOrdersActive ? ' aria-current="page"' : '' ?>>
                <i class="bi bi-box-seam<?= $isOrdersActive ? '-fill' : '' ?> customer-bottom-nav__icon" aria-hidden="true"></i>
                <span class="customer-bottom-nav__label">Orders</span>
            </a>
            <a href="<?= base_url('package-workflow') ?>" class="customer-bottom-nav__item<?= $isMessagesActive ? ' is-active' : '' ?>"<?= $isMessagesActive ? ' aria-current="page"' : '' ?>>
                <i class="bi bi-chat-dots<?= $isMessagesActive ? '-fill' : '' ?> customer-bottom-nav__icon" aria-hidden="true"></i>
                <span class="customer-bottom-nav__label">Messages</span>
            </a>
            <a href="<?= base_url('orders/new') ?>" class="customer-bottom-nav__item customer-bottom-nav__item--action<?= $isNewOrderActive ? ' is-active' : '' ?>"<?= $isNewOrderActive ? ' aria-current="page"' : '' ?> aria-label="Create new order">
                <div class="customer-bottom-nav__action-btn">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                </div>
                <span class="customer-bottom-nav__label">New</span>
            </a>
            <a href="<?= base_url('addresses') ?>" class="customer-bottom-nav__item<?= $isAddressesActive ? ' is-active' : '' ?>"<?= $isAddressesActive ? ' aria-current="page"' : '' ?>>
                <i class="bi bi-geo-alt<?= $isAddressesActive ? '-fill' : '' ?> customer-bottom-nav__icon" aria-hidden="true"></i>
                <span class="customer-bottom-nav__label">Addresses</span>
            </a>
            <a href="<?= base_url('profile') ?>" class="customer-bottom-nav__item<?= $isProfileActive ? ' is-active' : '' ?>"<?= $isProfileActive ? ' aria-current="page"' : '' ?>>
                <i class="bi bi-person<?= $isProfileActive ? '-fill' : '' ?> customer-bottom-nav__icon" aria-hidden="true"></i>
                <span class="customer-bottom-nav__label">Profile</span>
            </a>
        </nav>
    <?php endif; ?>

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
    <script src="<?= base_url('js/form-enhancer.js') ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="<?= base_url('js/ai-assistant.js') ?>"></script>
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
