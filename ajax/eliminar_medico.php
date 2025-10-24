<?php
// ajax/eliminar_medico.php — borrado en cascada sin fricción
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__.'/../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$res = ['success'=>false,'message'=>''];

function table_exists(PDO $con, string $t): bool {
  $st = $con->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
  $st->execute([':t'=>$t]); return (bool)$st->fetchColumn();
}
function has_col(PDO $con, string $t, string $c): bool {
  $st = $con->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1");
  $st->execute([':t'=>$t, ':c'=>$c]); return (bool)$st->fetchColumn();
}

try {
  $id = (int)($_POST['id_medico'] ?? 0);
  if ($id <= 0) throw new Exception('ID inválido');

  // Si te interesa mantener la opción vieja, respeta inactivar_si_ref
  $inactivar_si_ref = !empty($_POST['inactivar_si_ref']);
  if ($inactivar_si_ref) {
    $up = $con->prepare("UPDATE medicos SET estado = 0 WHERE id_medico = ? LIMIT 1");
    $up->execute([$id]);
    $res['success']=true; $res['message']='Médico marcado como INACTIVO.'; echo json_encode($res,JSON_UNESCAPED_UNICODE); exit;
  }

  // Borrado duro en cascada (sin pedir contraseña)
  $con->beginTransaction();

  // El orden importa si hay FKs. Borra dependientes primero.
  $tablas = [
    'recetas_medicas',
    'tratamientos',
    'diagnosticos',
    'citas_medicas',
    // si existe tabla pivote
    'medico_especialidad'
  ];
  foreach ($tablas as $t) {
    if (table_exists($con,$t) && has_col($con,$t,'id_medico')) {
      $del = $con->prepare("DELETE FROM `$t` WHERE id_medico = :id");
      $del->execute([':id'=>$id]);
    }
  }

  // Primero obtenemos los datos del médico para la auditoría
  $med = null;
  if (table_exists($con, 'auditoria')) {
    $stmt = $con->prepare("SELECT * FROM medicos WHERE id_medico = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $med = $stmt->fetch(PDO::FETCH_ASSOC);
  }

  // finalmente, borra el médico
  $delM = $con->prepare("DELETE FROM medicos WHERE id_medico = :id LIMIT 1");
  $delM->execute([':id'=>$id]);

  // === AUDITORÍA ===
  if (table_exists($con,'auditoria') && $med) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $uid = $_SESSION['user_id'] ?? null;

    // Convertimos snapshot a JSON
    $antes = json_encode($med ?? [], JSON_UNESCAPED_UNICODE);

    $qAud = $con->prepare("
      INSERT INTO auditoria (
        modulo, tabla, id_registro, accion,
        usuario_id, estado_resultante,
        antes_json, despues_json, ip, user_agent, creado_en
      )
      VALUES (
        'Médicos', 'medicos', :id_registro, 'DELETE',
        :usuario_id, 'inactivo',
        :antes_json, NULL, :ip, :user_agent, NOW()
      )
    ");

    $qAud->execute([
      ':id_registro' => $id,
      ':usuario_id'  => $uid,
      ':antes_json'  => $antes,
      ':ip'          => $ip,
      ':user_agent'  => $ua
    ]);
  }

  $con->commit();
  $res['success'] = true;
  $res['message'] = 'Médico eliminado junto con sus registros asociados.';

} catch (Throwable $e) {
  if (isset($con) && $con->inTransaction()) $con->rollBack();
  $res['success'] = false;
  $res['message'] = 'Error: '.$e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);