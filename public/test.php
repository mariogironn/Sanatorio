<?php
// Archivo temporal para testing
echo "<h1>¡El servidor funciona!</h1>";
echo "<p>Si ves este mensaje, PHP está funcionando correctamente.</p>";
echo "<p>Ahora prueba ir a: <a href='/sanatorio/public/'>/sanatorio/public/</a></p>";

// Mostrar información de debug
echo "<h3>Información de Debug:</h3>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "PHP Version: " . phpversion() . "<br>";
?>