<?php
class PacienteController {
    
    public function listar() {
        // Incluir conexión a la base de datos
        include '../config/connection.php';
        
        // Consulta para obtener pacientes
        $sql = "SELECT * FROM pacientes WHERE estado = 'activo' ORDER BY id_paciente DESC";
        $stmt = $con->prepare($sql);
        $stmt->execute();
        $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Incluir la vista
        include '../app/views/pacientes/listar.php';
    }
    
    public function crear() {
        // Lógica para crear paciente
        if ($_POST) {
            include '../config/connection.php';
            
            $sql = "INSERT INTO pacientes (nombre, direccion, dpi, fecha_nacimiento, telefono, genero, tipo_sangre, estado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'activo')";
            $stmt = $con->prepare($sql);
            $stmt->execute([
                $_POST['nombre'],
                $_POST['direccion'],
                $_POST['dpi'],
                $_POST['fecha_nacimiento'],
                $_POST['telefono'],
                $_POST['genero'],
                $_POST['tipo_sangre']
            ]);
            
            header('Location: /sanatorio/public/pacientes');
            exit;
        }
        
        include '../app/views/pacientes/crear.php';
    }
    
    public function editar() {
        $id = $_GET['id'] ?? 0;
        
        if ($_POST) {
            include '../config/connection.php';
            
            $sql = "UPDATE pacientes SET nombre=?, direccion=?, dpi=?, fecha_nacimiento=?, telefono=?, genero=?, tipo_sangre=?
                    WHERE id_paciente=?";
            $stmt = $con->prepare($sql);
            $stmt->execute([
                $_POST['nombre'],
                $_POST['direccion'],
                $_POST['dpi'],
                $_POST['fecha_nacimiento'],
                $_POST['telefono'],
                $_POST['genero'],
                $_POST['tipo_sangre'],
                $id
            ]);
            
            header('Location: /sanatorio/public/pacientes');
            exit;
        }
        
        // Obtener datos del paciente
        include '../config/connection.php';
        $sql = "SELECT * FROM pacientes WHERE id_paciente = ?";
        $stmt = $con->prepare($sql);
        $stmt->execute([$id]);
        $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        include '../app/views/pacientes/editar.php';
    }
}
?>