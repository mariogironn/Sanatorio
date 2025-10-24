<?php
// ajax/enfermedad_guardar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/connection.php';

$out = ['success' => false, 'message' => ''];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    throw new Exception('Método no permitido');
  }

  // Datos del formulario
  $id           = (int)($_POST['id'] ?? 0);
  $nombre       = trim($_POST['nombre'] ?? '');
  $cie10        = trim($_POST['cie10'] ?? '');
  $categoria_id = (int)($_POST['categoria_id'] ?? 0);
  $estado       = strtolower(trim($_POST['estado'] ?? 'activa'));
  $descripcion  = trim($_POST['descripcion'] ?? '');
  $banderas     = $_POST['banderas'] ?? [];

  if ($nombre === '' || $descripcion === '') {
    http_response_code(400);
    throw new Exception('Faltan datos obligatorios (nombre/descripcion).');
  }

  if ($estado !== 'activa' && $estado !== 'inactiva') {
    $estado = 'activa';
  }

  // Normalizar banderas a enteros únicos
  $banderas = array_values(array_unique(array_map('intval', (array)$banderas)));

  $con->beginTransaction();

  if ($id > 0) {
    // UPDATE
    $sql = "UPDATE enfermedades
              SET nombre = :nombre,
                  cie10 = :cie10,
                  categoria_id = :categoria_id,
                  descripcion = :descripcion,
                  estado = :estado,
                  updated_at = NOW()
            WHERE id = :id";
    $st = $con->prepare($sql);
    $st->execute([
      ':nombre'       => $nombre,
      ':cie10'        => $cie10 ?: null,
      ':categoria_id' => $categoria_id ?: null,
      ':descripcion'  => $descripcion,
      ':estado'       => $estado,
      ':id'           => $id
    ]);
  } else {
    // INSERT
    $sql = "INSERT INTO enfermedades
              (nombre, cie10, categoria_id, descripcion, estado, created_at)
            VALUES
              (:nombre, :cie10, :categoria_id, :descripcion, :estado, NOW())";
    $st = $con->prepare($sql);
    $st->execute([
      ':nombre'       => $nombre,
      ':cie10'        => $cie10 ?: null,
      ':categoria_id' => $categoria_id ?: null,
      ':descripcion'  => $descripcion,
      ':estado'       => $estado
    ]);
    $id = (int)$con->lastInsertId();
  }

  // Sincronizar banderas (tabla puente)
  $del = $con->prepare("DELETE FROM enfermedad_banderas WHERE enfermedad_id = ?");
  $del->execute([$id]);

  if (!empty($banderas)) {
    $ins = $con->prepare("INSERT INTO enfermedad_banderas (enfermedad_id, bandera_id) VALUES (?, ?)");
    foreach ($banderas as $b) {
      if ($b > 0) { $ins->execute([$id, $b]); }
    }
  }

  $con->commit();
  $out['success'] = true;
  $out['id'] = $id;
  $out['message'] = 'Enfermedad guardada correctamente.';
} catch (Throwable $e) {
  if ($con && $con->inTransaction()) { $con->rollBack(); }
  if (!http_response_code()) { http_response_code(400); }
  $out['message'] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
