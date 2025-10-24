<?php
// config/connection.php
// Conexión PDO + sesión, segura y con UTF-8 completo

// Iniciar sesión una sola vez
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// ===== Parámetros de BD =====
$host     = 'localhost';
$port     = 3306;
$db       = 'la_esperanza';
$user     = 'root';
$password = '';
$charset  = 'utf8mb4';

// DSN con charset para evitar SET NAMES manual
$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

// Opciones recomendadas
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // lanza excepciones
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // fetch asociativo
  PDO::ATTR_EMULATE_PREPARES   => false,                  // prepara real en MySQL
  // PDO::ATTR_PERSISTENT      => true,                   // (opcional) conexiones persistentes
];

try {
  $con = new PDO($dsn, $user, $password, $options);

  // (Opcional) forzar modo estricto en MySQL:
  // $con->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

  // (Opcional) zona horaria por conexión (Guatemala, UTC-6 sin DST):
  // $con->exec("SET time_zone = '-06:00'");

  // Nota: Se eliminó el SET @app_user_id porque ya no se usan triggers.
} catch (PDOException $e) {
  // Mensaje corto y seguro (no exponer credenciales/stacktrace en producción)
  // Para depurar temporalmente, descomenta la línea de abajo:
  // die('Error de conexión: ' . $e->getMessage());
  die('Error de conexión a la base de datos.');
}

// 24 minutes default idle time (tu comentario original)
// if (isset($_SESSION['ABC'])) { unset($_SESSION['ABC']); }
