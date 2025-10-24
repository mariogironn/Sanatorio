<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([]);
    exit;
}

$medicinasIds = $_POST['medicinas'] ?? [];

if (empty($medicinasIds) || count($medicinasIds) < 2) {
    echo json_encode([]);
    exit;
}

// Obtener nombres de las medicinas seleccionadas
$placeholders = str_repeat('?,', count($medicinasIds) - 1) . '?';
$queryNombres = "SELECT id, nombre_medicamento FROM medicamentos WHERE id IN ($placeholders)";
$stmtNombres = $pdo->prepare($queryNombres);
$stmtNombres->execute($medicinasIds);
$nombresMedicinas = $stmtNombres->fetchAll(PDO::FETCH_ASSOC);

// Crear mapeo ID -> Nombre
$mapeoNombres = [];
foreach ($nombresMedicinas as $med) {
    $mapeoNombres[$med['id']] = $med['nombre_medicamento'];
}

// Verificar interacciones entre las medicinas seleccionadas
$interaccionesEncontradas = [];

// Para cada par de medicinas, verificar si hay interacción
for ($i = 0; $i < count($medicinasIds); $i++) {
    for ($j = $i + 1; $j < count($medicinasIds); $j++) {
        $medA = $medicinasIds[$i];
        $medB = $medicinasIds[$j];
        
        // Buscar interacción en la base de datos
        $queryInteraccion = "
            SELECT im.*, 
                   ma.nombre_medicamento as nombre_a, 
                   mb.nombre_medicamento as nombre_b
            FROM interacciones_medicamentos im
            JOIN medicamentos ma ON im.id_medicamento_a = ma.id
            JOIN medicamentos mb ON im.id_medicamento_b = mb.id
            WHERE ((im.id_medicamento_a = ? AND im.id_medicamento_b = ?) 
                   OR (im.id_medicamento_a = ? AND im.id_medicamento_b = ?))
            AND im.estado = 1
        ";
        
        $stmtInteraccion = $pdo->prepare($queryInteraccion);
        $stmtInteraccion->execute([$medA, $medB, $medB, $medA]);
        $interaccion = $stmtInteraccion->fetch(PDO::FETCH_ASSOC);
        
        if ($interaccion) {
            $interaccionesEncontradas[] = [
                'medicamento_a' => $interaccion['nombre_a'],
                'medicamento_b' => $interaccion['nombre_b'],
                'severidad' => $interaccion['severidad'],
                'nota' => $interaccion['nota']
            ];
        }
    }
}

echo json_encode($interaccionesEncontradas);
?>