<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
?>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-top py-3 mt-auto">
        <div class="container d-flex flex-wrap justify-content-between align-items-center text-muted" style="font-size: 0.875rem;">
            <div>
                &copy; <?= date('Y') ?> <strong>SwiftLogix</strong> Delivery & Logistics Management System.
            </div>
            <div>
                <span class="badge bg-light text-secondary border">PHP 8.1+ LavaLust</span>
                <span class="badge bg-light text-secondary border">Bootstrap 5</span>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Disable submit button on form submission to prevent double clicks
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const btn = form.querySelector('button[type="submit"]');
                if (btn && !form.hasAttribute('data-no-disable')) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
                }
            });
        });
    </script>
    <?php if (isset($extra_js)): ?>
        <?= $extra_js ?>
    <?php endif; ?>
</body>
</html>
