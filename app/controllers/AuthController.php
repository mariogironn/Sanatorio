<?php
class AuthController {
    
    public function login() {
        echo "<h1>¡LOGIN FUNCIONA!</h1>";
        echo "<p>El AuthController está trabajando correctamente.</p>";
        echo '<p><a href="/sanatorio/public/">Volver al inicio</a></p>';
        
        // Incluir la vista real del login
        include '../app/views/auth/login.php';
    }
}
?>