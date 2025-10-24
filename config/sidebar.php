<?php
/*  Sidebar con control por permisos
    - Mostramos/ocultamos bloques según $_SESSION['permisos'][slug]['ver'].
*/
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// Imagen de perfil por defecto
$imagen = 'user_images/default-user.png';

// Si hay imagen en sesión y existe el archivo, úsala
if (!empty($_SESSION['imagen_perfil'])) {
  $rutaImagen = 'user_images/' . $_SESSION['imagen_perfil'];
  if (is_file($rutaImagen)) { $imagen = $rutaImagen; }
}

// Rol mostrado debajo del nombre (si lo guardas en sesión)
$rolNombre = $_SESSION['rol_nombre'] ?? '';

// === Helper de permisos ===
$__permisos = $_SESSION['permisos'] ?? [];
$canView = function(string $slug) use ($__permisos): bool {
  if (!isset($__permisos[$slug])) return true;
  return (int)($__permisos[$slug]['ver'] ?? 1) === 1;
};
?>

<!-- Estilos locales SOLO para el panel de usuario + reloj -->
<style>
  /* ----- Vista normal (sidebar expandido) ----- */
  .main-sidebar .user-panel.user-lg .image img{
    width:60px; height:60px; object-fit:cover;
  }
  .main-sidebar .user-panel.user-lg .info .user-name{
    font-size:1.1rem; font-weight:600;
  }
  .main-sidebar .user-panel.user-lg .info .role-badge{
    font-size:12.5px; padding:.28rem .6rem; border-radius:12px;
  }

  /* Reloj en el header del sidebar */
  #sbClock{
    position:absolute; left:12px; top:50%; transform:translateY(-50%);
    display:flex; align-items:center; color:#fff; pointer-events:none;
  }
  #sbClock i{ margin-right:6px; opacity:.9; }
  #sbClock .sbclock-text{ font-weight:600; letter-spacing:.3px; }

  /* ----- Vista colapsada (sidebar-mini) ----- */
  .sidebar-mini.sidebar-collapse .main-sidebar .user-panel.user-lg{
    justify-content:center;
  }
  .sidebar-mini.sidebar-collapse .main-sidebar .user-panel.user-lg .image{
    margin:0 auto !important;
  }
  .sidebar-mini.sidebar-collapse .main-sidebar .user-panel.user-lg .image img{
    width:42px; height:42px;
  }
  .sidebar-mini.sidebar-collapse .main-sidebar .user-panel.user-lg .info{
    display:none !important;
  }

  /* En colapsado, mostrar solo el ícono del reloj y compactarlo */
  .sidebar-mini.sidebar-collapse #sbClock .sbclock-text{ display:none; }
  .sidebar-mini.sidebar-collapse #sbClock i{ margin-right:0; font-size:1rem; }
</style>

<!-- Sidebar -->
<aside class="main-sidebar sidebar-dark-primary bg-black elevation-4">

  <!-- Logo / Header del sidebar -->
  <a href="dashboard.php" class="brand-link logo-switch bg-black" style="position:relative;">
    <h4 class="brand-image-xl logo-xs mb-0 text-center"><b></b></h4>

    <!-- RELOJ (solo hora) en el lugar de "Bienvenido" -->
    <span id="sbClock">
      <i class="fas fa-clock"></i>
      <span class="sbclock-text"></span>
    </span>

    <!-- Botón hamburguesa del sidebar -->
    <span
      class="text-white"
      title="Mostrar/Ocultar menú"
      aria-label="Mostrar/Ocultar menú"
      data-widget="pushmenu"
      role="button"
      style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer;"
    >
      <i class="fas fa-bars"></i>
    </span>
  </a>

  <!-- Contenido del sidebar -->
  <div class="sidebar">
    <!-- Panel de usuario (foto + nombre + rol) -->
    <div class="user-panel mt-3 pb-3 mb-3 d-flex user-lg">
      <div class="image">
        <img src="<?php echo $imagen; ?>" class="img-circle elevation-2" alt="User Image" />
      </div>
      <div class="info ml-3" style="line-height:1.2;">
        <div class="d-block text-white user-name">
          <?php echo htmlspecialchars($_SESSION['nombre_mostrar'] ?? ''); ?>
        </div>
        <?php if ($rolNombre !== ''): ?>
          <div class="mt-1">
            <span class="badge badge-warning role-badge">
              <?php echo htmlspecialchars($rolNombre); ?>
            </span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Menú -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

        <?php if ($canView('dashboard')): ?>
        <li class="nav-item" id="mnu_dashboard">
          <a href="dashboard.php" class="nav-link">
            <i class="fas fa-chart-line nav-icon"></i>
            <p>Menu</p>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($canView('pacientes')): ?>
        <li class="nav-item" id="mnu_patients">
          <a href="#" class="nav-link">
            <i class="fas fa-user-md nav-icon"></i>
            <p>Pacientes <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="nueva_prescripcion.php" class="nav-link" id="mi_prescriptions">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Nueva Prescripción</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="pacientes.php" class="nav-link" id="mi_patients">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Agregar Pacientes</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="historial_paciente.php" class="nav-link" id="mi_patient_history">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Historial Paciente</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <!-- NUEVO MÓDULO: DIAGNÓSTICOS Y TRATAMIENTOS -->
        <?php if ($canView('diagnosticos')): ?>
        <li class="nav-item" id="mnu_diagnosticos">
          <a href="#" class="nav-link">
            <i class="fas fa-stethoscope nav-icon"></i>
            <p>Diagnósticos <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="enfermedades.php" class="nav-link" id="mi_enfermedades">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Enfermedades</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="diagnosticos.php" class="nav-link" id="mi_diagnosticos">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Diagnósticos</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="tratamientos.php" class="nav-link" id="mi_tratamientos">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Tratamientos</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <!-- MÓDULO: PERSONAL MÉDICO -->
        <?php if ($canView('personal_medico')): ?>
        <li class="nav-item" id="mnu_personal_medico">
          <a href="#" class="nav-link">
            <i class="fas fa-user-md nav-icon"></i>
            <p>Personal Médico <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="medicos.php" class="nav-link" id="mi_medicos">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Médicos</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="especialidades.php" class="nav-link" id="mi_especialidades">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Especialidades Medicas</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="horarios_medicos.php" class="nav-link" id="mi_horarios">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Horarios Médicos</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <?php if ($canView('medicinas')): ?>
        <li class="nav-item" id="mnu_medicines">
          <a href="#" class="nav-link">
            <i class="fas fa-pills nav-icon"></i>
            <p>Medicinas <i class="fas fa-angle-left right"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="medicinas.php" class="nav-link" id="mi_medicines">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Agregar Medicina</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="recetas_medicas.php" class="nav-link" id="mi_recetas">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Recetas Médicas</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="citas.php" class="nav-link" id="mi_appointments">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Citas Médicas</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <?php if ($canView('reportes')): ?>
        <li class="nav-item" id="mnu_reports">
          <a href="#" class="nav-link">
            <i class="fas fa-clipboard-list nav-icon"></i>
            <p>Reportes <i class="fas fa-angle-left right"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <?php if ($canView('reportes.auditoria')): ?>
            <li class="nav-item">
              <a href="reportes_auditoria.php" class="nav-link" id="mi_reports_auditoria">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Auditoría</p>
              </a>
            </li>
            <?php endif; ?>
            <?php if ($canView('reportes.comparativo')): ?>
            <li class="nav-item">
              <a href="reporte_comparativo.php" class="nav-link" id="mi_reports_comparativo">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Comparar Pacientes</p>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if ($canView('usuarios')): ?>
        <li class="nav-item" id="mnu_users">
          <a href="#" class="nav-link">
            <i class="fas fa-users nav-icon"></i>
            <p>Usuarios <i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="usuarios.php" class="nav-link" id="mi_users_list">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Usuarios</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="usuarios_roles.php" class="nav-link" id="mi_users_roles">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Roles y Permisos</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="usuarios_sucursales.php" class="nav-link" id="mi_users_branches">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>Acceso por Sucursales</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <!-- Cerrar sesión -->
        <li class="nav-item nav-logout">
          <a href="logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt nav-icon" style="color:#ff8c00;"></i>
            <p>Cerrar Sesión</p>
          </a>
        </li>

      </ul>
    </nav>
  </div>
</aside>

<script>
/* Activa el menú correcto, abre el grupo y asegura "has-treeview" */
(function(){
  var page = (location.pathname.split('/').pop() || '').split('#')[0].split('?')[0];

  // Cualquier <li> con <ul.nav-treeview> recibe la clase has-treeview
  document.querySelectorAll('.nav-sidebar > li.nav-item').forEach(function(li){
    if (li.querySelector('ul.nav-treeview')) li.classList.add('has-treeview');
  });

  // Marca activo por URL y abre el grupo padre
  document.querySelectorAll('.nav-sidebar a.nav-link[href]').forEach(function(a){
    var href = (a.getAttribute('href')||'').split('#')[0].split('?')[0];
    if (!href || href === '#' || href.indexOf('logout.php') !== -1) return;

    if (page === href) {
      a.classList.add('active');
      var li = a.closest('li.nav-item');
      if (li) li.classList.add('active');

      var ul = a.closest('ul.nav-treeview');
      if (ul) {
        var parent = ul.closest('li.nav-item');
        if (parent) {
          parent.classList.add('menu-open');
          var plink = parent.querySelector(':scope > a.nav-link');
          if (plink) plink.classList.add('active');
        }
      }
    }
  });
})();
</script>

<!-- Script del reloj (solo hora 12h con segundos y AM/PM) -->
<script>
(function(){
  function two(n){ return n<10 ? '0'+n : n; }
  function t12(d){
    var h=d.getHours(), m=d.getMinutes(), s=d.getSeconds();
    var ampm = h>=12 ? 'PM' : 'AM';
    h = h % 12; if (h===0) h = 12;
    return two(h)+':'+two(m)+':'+two(s)+' '+ampm;
  }
  function tick(){
    var el = document.querySelector('#sbClock .sbclock-text');
    if (el) el.textContent = t12(new Date());
  }
  document.addEventListener('DOMContentLoaded', tick);
  setInterval(tick, 1000);
})();
</script>