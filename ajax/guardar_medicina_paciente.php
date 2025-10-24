<?php
// ajax/guardar_medicina_paciente.php - GUARDAR ASIGNACIÓN DE MEDICINA A PACIENTE
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

include '../config/connection.php';
include '../common_service/common_functions.php';
include '../common_service/auditoria_service.php';

$response = ['success'=>false,'message'=>'Error desconocido','id'=>null];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // ====== AUTORIZACIÓN (roles + permiso CREAR en módulos Medicinas/Pacientes) ======
    $usuario_id = _audit_guess_user_id();
    if (!$usuario_id) { throw new Exception('Sesión inválida. Inicie sesión nuevamente.'); }

    // Roles del usuario
    $rs = $con->prepare("
        SELECT LOWER(r.nombre) AS rol
        FROM usuario_rol ur
        JOIN roles r ON r.id_rol = ur.id_rol
        WHERE ur.id_usuario = :u
    ");
    $rs->execute([':u'=>$usuario_id]);
    $roles = array_map(fn($x)=>$x['rol'], $rs->fetchAll(PDO::FETCH_ASSOC));
    $esPersonalMedico = (bool) array_intersect($roles, ['medico','doctor','enfermero','enfermera']);
    if (!$esPersonalMedico) {
        throw new Exception('No autorizado: su rol no permite recetar.');
    }

    // Permiso CREAR en módulos relevantes
    // (Usamos nombre del módulo; si manejas otros nombres, añade aquí)
    $modIds = [];
    $ms = $con->query("
        SELECT id_modulo FROM modulos 
        WHERE LOWER(nombre) IN ('medicinas','medicamentos','pacientes','prescripciones')
    ");
    if ($ms) { $modIds = $ms->fetchAll(PDO::FETCH_COLUMN); }

    $tienePermisoCrear = false;
    if ($modIds) {
        $qp = $con->prepare("
            SELECT 1
            FROM rol_permiso rp
            JOIN usuario_rol ur ON ur.id_rol = rp.id_rol
            WHERE ur.id_usuario = :u AND rp.id_modulo IN (".implode(',', array_map('intval',$modIds)).")
              AND rp.crear = 1
            LIMIT 1
        ");
        $qp->execute([':u'=>$usuario_id]);
        $tienePermisoCrear = (bool)$qp->fetchColumn();
    }
    if (!$tienePermisoCrear) {
        throw new Exception('No autorizado: sin permiso para crear prescripciones.');
    }
    // ====== FIN AUTORIZACIÓN ======

    // Campos requeridos
    $required_fields = ['paciente_id','medicina_id','enfermedad_diagnostico','motivo_prescripcion','dosis','frecuencia'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || trim((string)$_POST[$field]) === '') {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    // Datos
    $paciente_id            = (int)$_POST['paciente_id'];
    $medicina_id            = (int)$_POST['medicina_id'];
    $enfermedad_diagnostico = trim($_POST['enfermedad_diagnostico']);
    $motivo_prescripcion    = trim($_POST['motivo_prescripcion']);
    $dosis                  = trim($_POST['dosis']);
    $frecuencia             = trim($_POST['frecuencia']);
    $duracion_tratamiento   = isset($_POST['duracion_tratamiento']) ? trim($_POST['duracion_tratamiento']) : null;

    // Validaciones adicionales
    if ($paciente_id <= 0)  { throw new Exception('ID de paciente inválido'); }
    if ($medicina_id <= 0)  { throw new Exception('ID de medicina inválido'); }
    if (mb_strlen($enfermedad_diagnostico) < 3) { throw new Exception('El diagnóstico debe tener al menos 3 caracteres'); }
    if (mb_strlen($motivo_prescripcion)    < 10){ throw new Exception('El motivo de prescripción debe tener al menos 10 caracteres'); }

    // Existencia paciente (activo)
    $stmt = $con->prepare("SELECT id_paciente FROM pacientes WHERE id_paciente = ? AND estado = 'activo'");
    $stmt->execute([$paciente_id]);
    if (!$stmt->fetch()) { throw new Exception('El paciente no existe o está inactivo'); }

    // Existencia medicina
    $stmt = $con->prepare("SELECT id FROM medicamentos WHERE id = ?");
    $stmt->execute([$medicina_id]);
    if (!$stmt->fetch()) { throw new Exception('La medicina no existe'); }

    // Evitar duplicado activo exacto (misma medicina activa para el paciente)
    $stmt = $con->prepare("
        SELECT id FROM paciente_medicinas 
        WHERE paciente_id = ? AND medicina_id = ? AND estado = 'activo'
        LIMIT 1
    ");
    $stmt->execute([$paciente_id, $medicina_id]);
    if ($stmt->fetch()) {
        throw new Exception('Esta medicina ya está asignada activamente al paciente');
    }

    // Transacción
    $con->beginTransaction();
    try {
        // Insertar asignación (se fuerza usuario_id = usuario en sesión; ignoramos cualquier post externo)
        $sql = "INSERT INTO paciente_medicinas 
                (paciente_id, medicina_id, enfermedad_diagnostico, motivo_prescripcion, 
                 dosis, frecuencia, duracion_tratamiento, usuario_id, fecha_asignacion, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'activo')";
        $stmt = $con->prepare($sql);
        $stmt->execute([
            $paciente_id,
            $medicina_id,
            $enfermedad_diagnostico,
            $motivo_prescripcion,
            $dosis,
            $frecuencia,
            $duracion_tratamiento,
            $usuario_id
        ]);

        $nuevo_id = (int)$con->lastInsertId();
        if ($nuevo_id <= 0) { throw new Exception('No se pudo obtener el ID de la nueva asignación'); }

        // Auditoría
        $datos_asignacion = [
            'id'                     => $nuevo_id,
            'paciente_id'            => $paciente_id,
            'medicina_id'            => $medicina_id,
            'enfermedad_diagnostico' => $enfermedad_diagnostico,
            'motivo_prescripcion'    => $motivo_prescripcion,
            'dosis'                  => $dosis,
            'frecuencia'             => $frecuencia,
            'duracion_tratamiento'   => $duracion_tratamiento,
            'usuario_id'             => $usuario_id,
            'estado'                 => 'activo'
        ];
        audit_create($con, 'Medicinas Paciente', 'paciente_medicinas', $nuevo_id, $datos_asignacion, 'activo');

        $con->commit();

        $response = ['success'=>true,'message'=>'Medicina asignada correctamente al paciente','id'=>$nuevo_id];
    } catch (Exception $e) {
        if ($con->inTransaction()) { $con->rollBack(); }
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Error DB en guardar_medicina_paciente: ".$e->getMessage());
    $response['message'] = 'Error de base de datos';
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
