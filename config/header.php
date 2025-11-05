<?php
/**
 * Header principal del sistema Sanatorio
 * 
 * Este archivo maneja:
 * - Inicio de sesión PHP
 * - Gestión de sucursales activas
 * - Selector de sucursales para usuarios con múltiples sucursales
 * - Carga de configuración de reCAPTCHA
 * - Interfaz de navegación superior
 */

// Iniciar sesión si no está activa
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// Cargar configuración de reCAPTCHA para todas las vistas
require_once __DIR__ . '/recaptcha.php';

// Obtener sucursal activa actual y lista de sucursales permitidas
$sucAct  = isset($_SESSION['sucursal_activa']) ? (int)$_SESSION['sucursal_activa'] : 0;
$allowed = $_SESSION['sucursales_ids'] ?? [];

$sucList = []; // Array para almacenar la lista de sucursales

try {
    // Conectar a la base de datos
    require_once __DIR__ . '/connection.php';

    if (!empty($allowed)) {
        // Usuario tiene sucursales específicas asignadas
        $ph   = implode(',', array_fill(0, count($allowed), '?'));
        $stmt = $con->prepare("SELECT id, nombre FROM sucursales WHERE estado=1 AND id IN ($ph) ORDER BY nombre");
        $stmt->execute(array_map('intval', $allowed));
        $sucList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Validar y establecer sucursal activa si no es válida
        $allowedInts = array_map('intval', $allowed);
        if ($sucAct <= 0 || !in_array($sucAct, $allowedInts, true)) {
            $sucAct = $sucList[0]['id'] ?? 0;
            $_SESSION['sucursal_activa'] = $sucAct;
        }
    } else {
        // Usuario puede acceder a todas las sucursales activas
        $stmt = $con->query("SELECT id, nombre FROM sucursales WHERE estado=1 ORDER BY nombre");
        $sucList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Validar y establecer sucursal activa si no es válida
        $ids = array_map(fn($r)=> (int)$r['id'], $sucList);
        if ($sucAct <= 0 || !in_array($sucAct, $ids, true)) {
            $sucAct = $sucList[0]['id'] ?? 0;
            $_SESSION['sucursal_activa'] = $sucAct;
        }
    }
} catch (Throwable $e) { 
    // Manejar errores silenciosamente para no romper el header
    // En producción, podrías loggear el error: error_log($e->getMessage());
}
?>

<!-- ========== INICIO DEL NAVEGADOR PRINCIPAL ========== -->
<nav class="main-header navbar navbar-expand navbar-dark navbar-light fixed-top">

    <!-- SECCIÓN IZQUIERDA: Espacio reservado para elementos futuros -->
    <ul class="navbar-nav"></ul>

    <!-- SELECTOR DE SUCURSAL -->
    <div class="navbar-brand mb-0 pb-0" style="margin-right:.75rem;">
        <?php if (count($sucList) > 1): ?>
            <!-- Mostrar selector cuando hay múltiples sucursales -->
            <div class="input-group input-group-sm" style="min-width:240px;">
                <div class="input-group-prepend">
                    <span class="input-group-text bg-success text-white">
                        <i class="fas fa-store"></i>
                    </span>
                </div>
                <select id="selSucursalActiva" class="form-control form-control-sm rounded-0">
                    <?php foreach ($sucList as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>" 
                                <?php echo ((int)$s['id'] === $sucAct) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif (count($sucList) === 1): ?>
            <!-- Mostrar nombre único cuando solo hay una sucursal -->
            <div class="text-light d-flex align-items-center">
                <i class="fas fa-store text-success mr-2"></i>
                <span><b><?php echo htmlspecialchars($sucList[0]['nombre']); ?></b></span>
            </div>
        <?php else: ?>
            <!-- Espacio vacío cuando no hay sucursales -->
            <span class="brand-text font-weight-light"></span>
        <?php endif; ?>
    </div>

    <!-- SECCIÓN DERECHA: Información del usuario -->
    <ul class="navbar-nav ml-auto">
        <li class="nav-item mr-2">
            <div class="login-user text-light font-weight-bolder d-flex align-items-center">
                <i class="fas fa-home text-success mr-2"></i>
                <span class="mr-2">HOLA!</span>
                <span class="badge badge-success text-white">
                    <?php echo htmlspecialchars($_SESSION['nombre_mostrar'] ?? ''); ?>
                </span>
            </div>
        </li>
    </ul>
</nav>

<!-- ========== SCRIPT PARA CAMBIO DE SUCURSAL ========== -->
<script>
(function() {
    /**
     * Maneja el cambio de sucursal mediante AJAX
     */
    var sel = document.getElementById('selSucursalActiva');
    if (!sel) return;
    
    sel.addEventListener('change', function() {
        var id = parseInt(this.value, 10) || 0;
        if (!id) return;
        
        // Enviar solicitud para cambiar sucursal
        fetch('ajax/cambiar_sucursal.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: 'sucursal_id=' + encodeURIComponent(id)
        })
        .then(function(response) {
            return response.text();
        })
        .then(function(text) {
            if ((text || '').trim() === 'OK') {
                // Recargar página si el cambio fue exitoso
                location.reload();
            } else {
                alert(text || 'No se pudo cambiar la sucursal.');
            }
        })
        .catch(function() {
            alert('Error de conexión al cambiar sucursal.');
        });
    });
})();
</script>