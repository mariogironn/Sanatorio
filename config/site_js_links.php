<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- jQuery (full) -->
<script src="plugins/jquery/jquery.min.js"></script>

<!-- Bootstrap 4 (bundle con Popper) -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- AdminLTE App -->
<script src="dist/js/adminlte.min.js"></script>

<!-- ===== DataTables + Buttons (con Bootstrap 4) ===== -->
<!-- CSS (puede ir aquí sin problema) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">

<!-- Núcleo DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

<!-- Responsive (opcional pero útil) -->
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>

<!-- Buttons + dependencias para exportar -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<!-- Plugins propios -->
<script src="dist/js/jquery_confirm/jquery-confirm.js"></script>
<script src="dist/js/common_javascript_functions.js"></script>

<style>
  .sidebar-mini.sidebar-collapse .main-sidebar { width: 4.6rem !important; margin-left: 0 !important; }
  .sidebar-mini.sidebar-collapse .brand-link { width: 4.6rem !important; }
  .sidebar-mini.sidebar-collapse .brand-link .brand-text { display:none !important; }
  .sidebar-mini.sidebar-collapse .user-panel .info,
  .sidebar-mini.sidebar-collapse .nav-sidebar .nav-link p,
  .sidebar-mini.sidebar-collapse .nav-sidebar .nav-header { display: none !important; }
  .sidebar-mini.sidebar-collapse .content-wrapper,
  .sidebar-mini.sidebar-collapse .main-footer,
  .sidebar-mini.sidebar-collapse .main-header { margin-left: 4.6rem !important; }
</style>

<script>
(function () {
  function ensureMiniClass() {
    var b = document.body;
    if (!b.classList.contains('sidebar-mini')) b.classList.add('sidebar-mini');
  }
  function setState(collapsed) {
    var b = document.body;
    ensureMiniClass();
    if (collapsed) b.classList.add('sidebar-collapse');
    else b.classList.remove('sidebar-collapse');
    b.classList.remove('sidebar-open');
    document.documentElement.classList.remove('sidebar-open');
  }

  document.addEventListener('DOMContentLoaded', function () {
    var saved = localStorage.getItem('sbState'); // 'collapsed' | 'expanded'
    setState(saved === 'collapsed');

    // ÚNICO botón: el que va junto a "Bienvenido"
    var btn = document.getElementById('btnToggleMenu');
    if (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var collapsed = !document.body.classList.contains('sidebar-collapse');
        setState(collapsed);
        localStorage.setItem('sbState', collapsed ? 'collapsed' : 'expanded');
      });
    }
  });
})();
</script>
