<?php
echo "<h1>DEBUG - Información del Servidor</h1>";

echo "<h3>Variables del SERVER:</h3>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NO DEFINIDO') . "<br>";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'NO DEFINIDO') . "<br>";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'NO DEFINIDO') . "<br>";
echo "PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'NO DEFINIDO') . "<br>";

echo "<h3>Probando diferentes URLs:</h3>";
echo '<a href="/sanatorio/public/">/sanatorio/public/</a><br>';
echo '<a href="/sanatorio/public/index.php">/sanatorio/public/index.php</a><br>';
echo '<a href="/sanatorio/public/login">/sanatorio/public/login</a><br>';

echo "<h3>Probando acceso directo:</h3>";
echo '<form action="/sanatorio/public/debug.php" method="post">
        <input type="text" name="test" value="test">
        <button type="submit">Probar POST</button>
      </form>';
?>