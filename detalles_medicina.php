<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once '../config/connection.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('ID inválido'); }

// Traer medicina (con campos extra si existen)
$med = [];
try {
  $med = $con->prepare("SELECT * FROM medicamentos WHERE id = :id")->execute([':id'=>$id]) ? 
         $con->query("SELECT * FROM medicamentos WHERE id = {$id} LIMIT 1")->fetch(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) { $med = []; }

if (!$med) { echo '<em>No existe la medicina.</em>'; exit; }

// Pacientes activos que la usan
$pac = [];
try {
  $st = $con->prepare("
    SELECT p.nombre AS paciente, pm.dosis, pm.frecuencia, pm.motivo_prescripcion, u.nombre_mostrar AS medico
    FROM paciente_medicinas pm
    JOIN pacientes p ON p.id_paciente = pm.paciente_id
    LEFT JOIN usuarios u ON u.id = pm.usuario_id
    WHERE pm.medicina_id = :id AND pm.estado = 'activo'
    ORDER BY pm.fecha_asignacion DESC
  ");
  $st->execute([':id'=>$id]);
  $pac = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $pac = []; }

// Render
?>
<div>
  <h5><span class="badge badge-primary"><?= htmlspecialchars($med['nombre_medicamento']) ?></span></h5>
  <p><b>Principio Activo:</b> <?= htmlspecialchars($med['principio_activo'] ?? '—') ?></p>
  <p><b>Concentración:</b> <?= htmlspecialchars($med['concentracion'] ?? '—') ?></p>
  <p><b>Stock Actual:</b> <?= (int)($med['stock_actual'] ?? 0) ?> &nbsp; <b>Stock Mínimo:</b> <?= (int)($med['stock_minimo'] ?? 0) ?></p>
  <p><b>Tipo:</b> <span class="badge badge-<?= ($med['tipo_medicamento'] ?? '')==='controlado'?'warning':'info' ?>">
    <?= ($med['tipo_medicamento'] ?? 'no_controlado')==='controlado'?'controlado':'no_controlado' ?></span></p>

  <hr>
  <h6>Pacientes que usan esta medicina:</h6>
  <?php if (!$pac): ?>
    <em>No hay pacientes activos.</em>
  <?php else: ?>
    <?php foreach ($pac as $r): ?>
      <div class="mb-3">
        <strong><?= htmlspecialchars($r['paciente']) ?></strong><br>
        <?= htmlspecialchars($r['dosis']) ?> — <?= htmlspecialchars($r['frecuencia']) ?><br>
        <small class="text-muted">Para: <?= htmlspecialchars($r['motivo_prescripcion'] ?? '') ?> (Por: <?= htmlspecialchars($r['medico'] ?? '—') ?>)</small>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
