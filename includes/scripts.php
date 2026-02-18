<!-- Jquery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Global Main JS -->
<script src="/Project2026/assets/js/main.js"></script>

<!-- Page Loader HTML (Embedded here for convenience) -->
<div id="page-loader">
    <div class="loader-spinner"></div>
</div>

<!-- Global: Loading state for form submit buttons -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('button[type="submit"], button[name="submit"]');
                if (btn && !btn.disabled) {
                    var originalHTML = btn.innerHTML;
                    // Defer disable so browser captures form data (including button name) first
                    setTimeout(function () {
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังดำเนินการ...';
                        // Re-enable after 8s fallback (in case submit fails)
                        setTimeout(function () {
                            btn.disabled = false;
                            btn.innerHTML = originalHTML;
                        }, 8000);
                    }, 0);
                }
            });
        });
    });
</script>