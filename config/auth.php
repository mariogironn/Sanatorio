<?php
/**
 * config/auth.php
 * Utilidades de autenticación reutilizables para el sistema.
 *
 * Incluye:
 *  - current_user_id(), current_user_name(), require_login()
 *  - set_login_session(), clear_login_session()
 *  - is_md5_hash(), hash_password()
 *  - login_verify_and_rehash(PDO $pdo, string $usuario, string $clavePlano): ?array
 *
 * Notas:
 *  - Ejecutar una vez en BD: ALTER TABLE usuarios MODIFY contrasena VARCHAR(255) NOT NULL;
 *  - login_verify_and_rehash admite cuentas antiguas con MD5 y las migra a password_hash().
 */

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// Si tu login usa otros nombres de clave de sesión, añádelos aquí:
const SESSION_ID_KEYS   = ['user_id', 'id_usuario', 'usuario_id'];
const SESSION_NAME_KEYS = ['user_name', 'usuario', 'nombre_mostrar'];

/** Devuelve el ID del usuario logueado; 0 si no hay sesión */
function current_user_id(): int {
    foreach (SESSION_ID_KEYS as $k) {
        if (!empty($_SESSION[$k])) return (int)$_SESSION[$k];
    }
    return 0;
}

/** (Opcional) Nombre de usuario para logs/mensajes */
function current_user_name(): string {
    foreach (SESSION_NAME_KEYS as $k) {
        if (!empty($_SESSION[$k])) return (string)$_SESSION[$k];
    }
    return '';
}

/** Bloquea acceso si no hay sesión */
function require_login(): void {
    if (current_user_id() <= 0) {
        http_response_code(401);
        echo 'No autenticado';
        exit;
    }
}

/** Setea la sesión con claves estándar (ajusta si usas otras) */
function set_login_session(array $user): void {
    $_SESSION['user_id']        = (int)($user['id'] ?? 0);
    $_SESSION['usuario']        = (string)($user['usuario'] ?? '');
    $_SESSION['nombre_mostrar'] = (string)($user['nombre_mostrar'] ?? '');
}

/** Limpia la sesión de login (logout básico) */
function clear_login_session(): void {
    foreach (['user_id','id_usuario','usuario_id','usuario','user_name','nombre_mostrar'] as $k) {
        unset($_SESSION[$k]);
    }
    // Si quieres destruir toda la sesión (cuidado si guardas otros datos de app):
    // session_destroy();
}

/** Detecta si un hash parece MD5 (32 caracteres hex) */
function is_md5_hash(string $hash): bool {
    return preg_match('/^[a-f0-9]{32}$/i', $hash) === 1;
}

/** Hashea contraseñas nuevas (usa esto en altas/ediciones) */
function hash_password(string $plain): string {
    return password_hash($plain, PASSWORD_DEFAULT);
}

/**
 * Verifica credenciales y migra MD5 -> password_hash() automáticamente.
 * Retorna el registro del usuario (array) si el login es válido; null si falla.
 *
 * Uso:
 *   $u = login_verify_and_rehash($con, $usuario, $clave);
 *   if ($u) { set_login_session($u); ... }
 */
function login_verify_and_rehash(PDO $pdo, string $usuario, string $clavePlano): ?array {
    // 1) Traer usuario
    $stmt = $pdo->prepare("SELECT id, usuario, nombre_mostrar, estado, contrasena
                           FROM usuarios
                           WHERE usuario = :u
                           LIMIT 1");
    $stmt->execute([':u' => $usuario]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    // Opcional: bloquear usuarios inactivos/bloqueados
    if (isset($row['estado']) && strtoupper((string)$row['estado']) !== 'ACTIVO') {
        return null;
    }

    $hashDB  = (string)$row['contrasena'];
    $loginOK = false;

    // 2) Compatibilidad: hash MD5 antiguo
    if (is_md5_hash($hashDB)) {
        if (md5($clavePlano) === $hashDB) {
            $loginOK = true;
            // Re-hash al formato moderno
            $nuevoHash = password_hash($clavePlano, PASSWORD_DEFAULT);
            $up = $pdo->prepare("UPDATE usuarios SET contrasena = :h WHERE id = :id");
            $up->execute([':h' => $nuevoHash, ':id' => (int)$row['id']]);
            $row['contrasena'] = $nuevoHash;
        }
    } else {
        // 3) Ruta moderna
        if (password_verify($clavePlano, $hashDB)) {
            $loginOK = true;
            // 4) Rehash por mantenimiento si el coste cambió
            if (password_needs_rehash($hashDB, PASSWORD_DEFAULT)) {
                $nuevoHash = password_hash($clavePlano, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE usuarios SET contrasena = :h WHERE id = :id")
                    ->execute([':h' => $nuevoHash, ':id' => (int)$row['id']]);
                $row['contrasena'] = $nuevoHash;
            }
        }
    }

    return $loginOK ? $row : null;
}
