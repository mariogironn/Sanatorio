<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nuevo Paciente - SANATORIO</title>
    <link rel="stylesheet" href="/sanatorio/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/sanatorio/dist/css/adminlte.min.css">
</head>
<body>
    <div class="container-fluid">
        <h1>Nuevo Paciente</h1>
        
        <form action="/sanatorio/public/pacientes/guardar" method="post">
            <div class="card">
                <div class="card-body">
                    <div class="form-group">
                        <label>Nombre:</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Teléfono:</label>
                        <input type="text" name="telefono" class="form-control">
                    </div>
                </div>
                
                <div class="card-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Guardar Paciente
                    </button>
                    <a href="/sanatorio/public/pacientes" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script src="/sanatorio/plugins/jquery/jquery.min.js"></script>
    <script src="/sanatorio/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>