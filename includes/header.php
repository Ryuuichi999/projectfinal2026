<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="/Project2026/assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
<link href="/Project2026/assets/css/dynamic.css" rel="stylesheet">
<script>
// Restore sidebar state immediately to prevent flash
(function(){
    try {
        if(localStorage.getItem('sidebarCollapsed')==='true'){
            document.documentElement.classList.add('sidebar-collapsed');
        }
    } catch(e){}
})();
</script>
<style>
/* When sidebar-collapsed is on <html>, also apply to body immediately */
.sidebar-collapsed body .sidebar,
html.sidebar-collapsed .sidebar { transform: translateX(-260px) !important; }
html.sidebar-collapsed .topbar { left: 0 !important; }
html.sidebar-collapsed .content { margin-left: 0 !important; }
</style>