// Page Transition & Loader
document.addEventListener('DOMContentLoaded', function() {
    // Show body content
    document.body.classList.add('loaded');
    
    // Hide loader
    const loader = document.getElementById('page-loader');
    if(loader) {
        setTimeout(() => {
            loader.classList.add('hidden');
        }, 300); // Small delay for smoothness
    }

    // Add lift effect to cards and buttons automatically
    document.querySelectorAll('.card, .btn').forEach(el => {
        if (!el.classList.contains('no-hover')) {
            el.classList.add('hover-lift');
        }
        if (el.tagName === 'BUTTON' || (el.tagName === 'A' && el.classList.contains('btn'))) {
             el.classList.add('btn-slide');
        }
    });

    // Handle Link Clicks for Transition
    document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            // Filter out internal links, hashes, or target blank
            if (!href || href.startsWith('#') || href.startsWith('javascript:') || this.target === '_blank') return;

            e.preventDefault();
            document.body.style.opacity = '0';
            
            setTimeout(() => {
                window.location.href = href;
            }, 200); // Wait for fade out
        });
    });

    // Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        const toggleSidebar = () => document.body.classList.toggle('sidebar-collapsed');
        sidebarToggle.addEventListener('click', toggleSidebar);
        // Fallback: also respond to Enter/Space
        sidebarToggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleSidebar();
            }
        });
    }

    // Ensure Bootstrap dropdowns initialize
    if (window.bootstrap) {
        document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(el => {
            try { new bootstrap.Dropdown(el); } catch (e) {}
        });
    }
});

// Helper for Confirmation
function confirmAction(title, text, confirmBtnText = 'ใช่, ทำรายการ', cancelBtnText = 'ยกเลิก', callback) {
    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmBtnText,
        cancelButtonText: cancelBtnText
    }).then((result) => {
        if (result.isConfirmed) {
            callback();
        }
    });
}
