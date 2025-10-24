<?php
class DashboardController {
    
    public function index() {
        echo "<h1>¡DASHBOARD FUNCIONA!</h1>";
        echo "<p>El DashboardController está trabajando.</p>";
        echo '<p><a href="/sanatorio/public/login">Ir al Login</a></p>';
        
        // Incluir la vista real del dashboard
        include '../app/views/dashboard/index.php';
    }
}
?>