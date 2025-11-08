<?php
// config/connection.php
// Conexión PDO + sesión, UTF-8 y zona horaria consistente (GT, UTC-06:00)

// --- Sesión ---
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// --- Zona horaria global de PHP ---
date_default_timezone_set('America/Guatemala'); // afecta a date(), DateTime, etc.

// --- Parámetros de BD ---
$host     = 'localhost';
$port     = 3306;
$db       = 'la_esperanza';
$user     = 'root';
$password = '';
$charset  = 'utf8mb4';

// DSN con charset (evita SET NAMES manual)
$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

// Opciones PDO recomendadas
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
  // PDO::ATTR_PERSISTENT      => true, // opcional
];

try {
  $con = new PDO($dsn, $user, $password, $options);

  // Alinear la sesión de MySQL a la misma zona (UTC-06:00)
  // Evita desfases al usar NOW(), TIMESTAMP, etc.
  $con->exec("SET time_zone = '-06:00'");

  // (Opcional) modo estricto
  // $con->exec(\"SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'\");
} catch (PDOException $e) {
  // No revelar detalles en producción
  // die('Error de conexión: ' . $e->getMessage());
  die('Error de conexión a la base de datos.');
}
