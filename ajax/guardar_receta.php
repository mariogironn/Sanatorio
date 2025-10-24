<?php
// ajax/guardar_receta.php - GUARDAR / EDITAR RECETA MÉDICA
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../config/connection.php';
require_once '../common_service/common_functions.php';
require_once '../common_service/auditoria_service.php';

$response = ['success'=>false,'message'=>'Error desconocido','id_receta'=>null,'numero_receta'=>''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // ====== AUTORIZACIÓN ======
    $usuario_id = _audit_guess_user_id();
    if (!$usuario_id) { 
        throw new Exception('Sesión inválida. Inicie sesión nuevamente.'); 
    }

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

    // Permiso vía matriz (rol_permiso.crear sobre módulo 'recetas')
    $tienePermisoMatriz = false;
    $mods = $con->query("SELECT id_modulo FROM modulos WHERE slug='recetas' OR nombre LIKE '%Receta%'")
               ->fetchAll(PDO::FETCH_COLUMN);
    if ($mods) {
        $mods = array_map('intval', $mods);
        $q = $con->prepare("
            SELECT 1
            FROM rol_permiso rp
            JOIN usuario_rol ur ON ur.id_rol = rp.id_rol
            WHERE ur.id_usuario = :u
              AND rp.id_modulo IN (".implode(',', $mods).")
              AND rp.crear = 1
            LIMIT 1
        ");
        $q->execute([':u'=>$usuario_id]);
        $tienePermisoMatriz = (bool)$q->fetchColumn();
    }

    if (!$esPersonalMedico && !$tienePermisoMatriz) {
        throw new Exception('No autorizado para crear/editar recetas médicas.');
    }

    // ====== VALIDACIÓN DE DATOS ======
    $id_receta   = (int)($_POST['id_receta']   ?? 0);
    $id_paciente = (int)($_POST['id_paciente'] ?? 0);
    $id_medico   = (int)($_POST['id_medico']   ?? 0);

    // medicamentos puede venir como array (x-www-form-urlencoded) o como JSON string
    $medicamentos = $_POST['medicamentos'] ?? [];
    if (is_string($medicamentos)) {
        $tmp = json_decode($medicamentos, true);
        if (json_last_error() === JSON_ERROR_NONE) { $medicamentos = $tmp; }
    }
    if (!is_array($medicamentos)) { $medicamentos = []; }

    // Si es personal médico, forzamos a que la receta quede con su propio user_id
    if ($esPersonalMedico) { $id_medico = $usuario_id; }

    // Validaciones básicas
    if ($id_paciente <= 0)  { throw new Exception('Seleccione un paciente válido'); }
    if ($id_medico   <= 0)  { throw new Exception('Seleccione un médico válido'); }
    if (count($medicamentos) === 0) { throw new Exception('Debe agregar al menos un medicamento'); }

    foreach ($medicamentos as $index => $med) {
        if (empty($med['nombre']) || empty($med['dosis'])) {
            throw new Exception("El medicamento #".($index+1)." debe tener nombre y dosis");
        }
        // sanidad rápida
        $medicamentos[$index]['nombre']     = trim($med['nombre']);
        $medicamentos[$index]['dosis']      = trim($med['dosis']);
        $medicamentos[$index]['duracion']   = trim((string)($med['duracion']   ?? ''));
        $medicamentos[$index]['frecuencia'] = trim((string)($med['frecuencia'] ?? ''));
    }

    // Verificar existencia del paciente (activo)
    $stmt = $con->prepare("SELECT id_paciente FROM pacientes WHERE id_paciente = ? AND estado = 'activo'");
    $stmt->execute([$id_paciente]);
    if (!$stmt->fetch()) { throw new Exception('El paciente no existe o está inactivo'); }

    // Verificar existencia del médico
    $stmt = $con->prepare("SELECT id FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$id_medico]);
    if (!$stmt->fetch()) { throw new Exception('El médico no existe'); }

    // ====== TRANSACCIÓN PRINCIPAL ======
    $con->beginTransaction();

    try {
        $numero_receta = '';

        if ($id_receta > 0) {
            // MODO EDICIÓN - solo recetas activas
            $stmt = $con->prepare("SELECT id_receta, numero_receta FROM recetas_medicas WHERE id_receta = ? AND estado = 'activa' LIMIT 1");
            $stmt->execute([$id_receta]);
            $rowR = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rowR) { throw new Exception('La receta no existe o no está activa'); }
            $numero_receta = $rowR['numero_receta'];

            // Actualizar receta
            $sqlUpdate = "UPDATE recetas_medicas 
                          SET id_paciente = ?, id_medico = ?, updated_by = ?, updated_at = NOW()
                          WHERE id_receta = ?";
            $stmt = $con->prepare($sqlUpdate);
            $stmt->execute([$id_paciente, $id_medico, $usuario_id, $id_receta]);

            // Eliminar medicamentos anteriores
            $stmt = $con->prepare("DELETE FROM detalle_recetas WHERE id_receta = ?");
            $stmt->execute([$id_receta]);

            $nuevo_id = $id_receta;

        } else {
            // MODO CREACIÓN - Nueva receta
            // Generar número de receta tipo REC-1000, REC-1001, ...
            $s = $con->query("SELECT COALESCE(MAX(CAST(SUBSTRING(numero_receta, 5) AS UNSIGNED)), 999) + 1 AS next_num FROM recetas_medicas");
            $nextNum = (int)($s->fetch(PDO::FETCH_ASSOC)['next_num'] ?? 1000);
            if ($nextNum < 1000) { $nextNum = 1000; }
            $numero_receta = 'REC-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            // Insertar receta principal
            $sqlInsert = "INSERT INTO recetas_medicas 
                          (numero_receta, id_paciente, id_medico, fecha_emision, estado, created_by)
                          VALUES (?, ?, ?, CURDATE(), 'activa', ?)";
            $stmt = $con->prepare($sqlInsert);
            $stmt->execute([$numero_receta, $id_paciente, $id_medico, $usuario_id]);

            $nuevo_id = (int)$con->lastInsertId();
            if ($nuevo_id <= 0) { throw new Exception('No se pudo obtener el ID de la nueva receta'); }
        }

        // ====== INSERTAR MEDICAMENTOS ======
        foreach ($medicamentos as $med) {
            // Buscar ID del medicamento por nombre
            $stmt = $con->prepare("SELECT id FROM medicamentos WHERE nombre_medicamento = ? LIMIT 1");
            $stmt->execute([$med['nombre']]);
            $id_medicamento = $stmt->fetchColumn();

            if (!$id_medicamento) {
                // Crear medicamento básico si no existe
                $stmt = $con->prepare("INSERT INTO medicamentos (nombre_medicamento, estado) VALUES (?, 'activo')");
                $stmt->execute([$med['nombre']]);
                $id_medicamento = $con->lastInsertId();
            }

            // Insertar en detalle_recetas
            $sqlDetalle = "INSERT INTO detalle_recetas 
                           (id_receta, id_medicamento, nombre_medicamento, dosis, duracion, frecuencia)
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $con->prepare($sqlDetalle);
            $stmt->execute([
                $nuevo_id,
                $id_medicamento,
                $med['nombre'],
                $med['dosis'],
                $med['duracion'] ?? '',
                $med['frecuencia'] ?? ''
            ]);
        }

        // ====== AUDITORÍA ======
        $datos_receta = [
            'id_receta' => $nuevo_id,
            'id_paciente' => $id_paciente,
            'id_medico' => $id_medico,
            'total_medicamentos' => count($medicamentos),
            'medicamentos' => $medicamentos
        ];

        if ($id_receta > 0) {
            audit_update($con, 'Recetas', 'recetas_medicas', $nuevo_id, ['id_receta' => $nuevo_id], $datos_receta, 'activa');
        } else {
            audit_create($con, 'Recetas', 'recetas_medicas', $nuevo_id, $datos_receta, 'activa');
        }

        $con->commit();

        $response = [
            'success'       => true,
            'message'       => $id_receta > 0 ? 'Receta actualizada correctamente' : 'Receta creada correctamente',
            'id_receta'     => $nuevo_id,
            'numero_receta' => $numero_receta
        ];

    } catch (Exception $e) {
        if ($con->inTransaction()) { $con->rollBack(); }
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Error DB en guardar_receta: " . $e->getMessage());
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
