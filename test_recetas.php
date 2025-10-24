<?php
// test_recetas.php - Archivo temporal para diagnóstico
echo "<h1>Verificación del Sistema</h1>";

// 1. Verificar si el archivo existe
$archivo_principal = 'recetas_medicas.php';
if (file_exists($archivo_principal)) {
    echo "✅ <strong>recetas_medicas.php</strong> EXISTE en esta carpeta<br>";
} else {
    echo "❌ <strong>recetas_medicas.php</strong> NO EXISTE en esta carpeta<br>";
}

// 2. Verificar archivos AJAX
$archivos_ajax = [
    'ajax/guardar_receta.php',
    'ajax/eliminar_receta.php', 
    'ajax/get_receta.php'
];

echo "<h3>Archivos AJAX:</h3>";
foreach ($archivos_ajax as $ajax) {
    if (file_exists($ajax)) {
        echo "✅ $ajax EXISTE<br>";
    } else {
        echo "❌ $ajax NO EXISTE<br>";
    }
}

// 3. Verificar conexión a BD
try {
    require_once 'config/connection.php';
    echo "✅ Conexión a BD: OK<br>";
    
    // Verificar tablas
    $tablas = ['recetas_medicas', 'detalle_recetas'];
    foreach ($tablas as $tabla) {
        $stmt = $con->query("SHOW TABLES LIKE '$tabla'");
        if ($stmt->fetch()) {
            echo "✅ Tabla <strong>$tabla</strong>: EXISTE<br>";
        } else {
            echo "❌ Tabla <strong>$tabla</strong>: NO EXISTE<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "<br>";
}

// 4. URL actual
echo "<h3>Información de URL:</h3>";
echo "URL actual: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Script ejecutado: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";

echo "<hr><h3>Próximos pasos:</h3>";
echo "1. Guarda este archivo como <strong>test_recetas.php</strong> en tu carpeta principal<br>";
echo "2. Accede a: <strong>http://localhost/sanatorio/test_recetas.php</strong><br>";
echo "3. Comparte los resultados para poder ayudarte mejor";
?>