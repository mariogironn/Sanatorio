<?php
// Incluir templates reales
include '../app/views/templates/header.php';
include '../app/views/templates/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Gestión de Pacientes</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active">Pacientes</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Lista de Pacientes</h3>
                            <div class="card-tools">
                                <a href="/sanatorio/public/pacientes/crear" class="btn btn-success btn-sm">
                                    <i class="fas fa-plus"></i> Nuevo Paciente
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="example1" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Teléfono</th>
                                        <th>Género</th>
                                        <th>Tipo Sangre</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pacientes as $paciente): ?>
                                    <tr>
                                        <td><?= $paciente['id_paciente'] ?></td>
                                        <td><?= htmlspecialchars($paciente['nombre']) ?></td>
                                        <td><?= htmlspecialchars($paciente['telefono']) ?></td>
                                        <td><?= htmlspecialchars($paciente['genero']) ?></td>
                                        <td><?= htmlspecialchars($paciente['tipo_sangre']) ?></td>
                                        <td>
                                            <a href="/sanatorio/public/pacientes/editar?id=<?= $paciente['id_paciente'] ?>" 
                                               class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i> Editar
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
include '../app/views/templates/footer.php';
?>