<?php
class Router {
    private $routes = [];
    
    // ✅ AGREGAR ESTE MÉTODO QUE FALTABA
    public function addRoute($route, $controller, $method) {
        $this->routes[$route] = ['controller' => $controller, 'method' => $method];
    }
    
    public function handleRequest() {
        // Ruta solicitada
        $request = $_SERVER['REQUEST_URI'];
        
        // Quitar la base del path
        $base_path = '/sanatorio/public';
        if (strpos($request, $base_path) === 0) {
            $request = substr($request, strlen($base_path));
        }
        
        // Si es vacío, es la raíz
        if ($request === '' || $request === '/') {
            $request = '/';
        }
        
        echo "<!-- Ruta detectada: $request -->";
        
        // ✅ CAMBIAR: Usar el sistema de rutas dinámicas en lugar del switch
        if (isset($this->routes[$request])) {
            $controller = $this->routes[$request]['controller'];
            $method = $this->routes[$request]['method'];
            $this->loadController($controller, $method);
        } else {
            $this->show404($request);
        }
    }
    
    private function loadController($controllerName, $methodName) {
        $controllerFile = "../app/controllers/{$controllerName}.php";
        
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            
            if (class_exists($controllerName)) {
                $controller = new $controllerName();
                
                if (method_exists($controller, $methodName)) {
                    $controller->$methodName();
                } else {
                    $this->showError("Método $methodName no existe en $controllerName");
                }
            } else {
                $this->showError("Clase $controllerName no encontrada");
            }
        } else {
            $this->showError("Archivo controlador $controllerFile no encontrado");
        }
    }
    
    private function show404($request) {
        http_response_code(404);
        echo "<h1>Página no encontrada</h1>";
        echo "<p>No existe: $request</p>";
        echo "<h3>Rutas disponibles:</h3>";
        echo "<ul>";
        foreach ($this->routes as $route => $config) {
            echo "<li><a href='$route'>$route</a> → {$config['controller']}::{$config['method']}()</li>";
        }
        echo "</ul>";
        echo '<p><a href="/sanatorio/public/">Volver al inicio</a></p>';
    }
    
    private function showError($message) {
        http_response_code(500);
        echo "<h1>Error en la aplicación</h1>";
        echo "<p>$message</p>";
        echo "<p><a href='/sanatorio/public/'>Volver al inicio</a></p>";
    }
}
?>