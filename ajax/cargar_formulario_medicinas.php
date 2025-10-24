<?php
session_start();
require_once '../config/database.php';

// Obtener lista de pacientes activos
$queryPacientes = "SELECT id_paciente, nombre, fecha_nacimiento FROM pacientes WHERE estado = 'activo' ORDER BY nombre";
$stmtPacientes = $pdo->prepare($queryPacientes);
$stmtPacientes->execute();
$pacientes = $stmtPacientes->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de medicamentos
$queryMedicamentos = "SELECT id, nombre_medicamento FROM medicamentos ORDER BY nombre_medicamento";
$stmtMedicamentos = $pdo->prepare($queryMedicamentos);
$stmtMedicamentos->execute();
$medicamentos = $stmtMedicamentos->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Selección de Paciente -->
<div class="form-group">
    <label for="selectPaciente"><strong>Seleccionar Paciente *</strong></label>
    <select class="form-control" id="selectPaciente" required>
        <option value="">-- Seleccione un paciente --</option>
        <?php foreach ($pacientes as $paciente): 
            $edad = (new DateTime())->diff(new DateTime($paciente['fecha_nacimiento']))->y;
        ?>
            <option value="<?php echo $paciente['id_paciente']; ?>">
                <?php echo htmlspecialchars($paciente['nombre']); ?> - <?php echo $edad; ?> años
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Enfermedad del Paciente -->
<div class="form-group">
    <label for="selectEnfermedad"><strong>Enfermedad/Diagnóstico *</strong></label>
    <select class="form-control" id="selectEnfermedad" required>
        <option value="">-- Seleccione la enfermedad --</option>
        <option value="artritis_reumatoide">Artritis Reumatoide</option>
        <option value="hipertension">Hipertensión Arterial</option>
        <option value="diabetes_tipo1">Diabetes Tipo 1</option>
        <option value="diabetes_tipo2">Diabetes Tipo 2</option>
        <option value="infeccion_respiratoria">Infección Respiratoria</option>
        <option value="dolor_cronico">Dolor Crónico</option>
        <option value="ansiedad">Trastorno de Ansiedad</option>
        <option value="depresion">Depresión</option>
        <option value="asma">Asma</option>
        <option value="epilepsia">Epilepsia</option>
        <option value="otro">Otro</option>
    </select>
</div>

<!-- Campo para otra enfermedad -->
<div class="form-group" id="grupoOtraEnfermedad" style="display: none;">
    <label for="otraEnfermedad"><strong>Especifique la enfermedad</strong></label>
    <input type="text" class="form-control" id="otraEnfermedad" placeholder="Describa la enfermedad o diagnóstico">
</div>

<!-- Selección de Medicinas -->
<div class="form-group">
    <label><strong>Seleccionar Medicinas *</strong></label>
    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
        <?php if (empty($medicamentos)): ?>
            <div class="alert alert-warning text-center py-2">
                No hay medicamentos registrados en el sistema.
            </div>
        <?php else: ?>
            <?php foreach ($medicamentos as $medicamento): ?>
                <div class="form-check">
                    <input class="form-check-input medicina-check" type="checkbox" 
                           value="<?php echo $medicamento['id']; ?>" 
                           id="med<?php echo $medicamento['id']; ?>">
                    <label class="form-check-label" for="med<?php echo $medicamento['id']; ?>">
                        <?php echo htmlspecialchars($medicamento['nombre_medicamento']); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Motivo de la Prescripción -->
<div class="form-group">
    <label for="motivoPrescripcion"><strong>Motivo de la Prescripción *</strong></label>
    <textarea class="form-control" id="motivoPrescripcion" rows="3" 
              placeholder="Describa por qué se receta esta medicina al paciente..."></textarea>
</div>

<!-- Información Adicional -->
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="dosis"><strong>Dosis *</strong></label>
            <input type="text" class="form-control" id="dosis" placeholder="Ej: 400mg, 500mg" required>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="frecuencia"><strong>Frecuencia *</strong></label>
            <input type="text" class="form-control" id="frecuencia" 
                   placeholder="Ej: Cada 8 horas, Una vez al día" required>
        </div>
    </div>
</div>

<!-- Duración del Tratamiento -->
<div class="form-group">
    <label for="duracion"><strong>Duración del Tratamiento</strong></label>
    <input type="text" class="form-control" id="duracion" 
           placeholder="Ej: 7 días, 1 mes, tratamiento crónico">
</div>

<script>
// Mostrar/ocultar campo "Otra enfermedad"
$('#selectEnfermedad').change(function() {
    if ($(this).val() === 'otro') {
        $('#grupoOtraEnfermedad').show();
    } else {
        $('#grupoOtraEnfermedad').hide();
    }
});

// Verificar interacciones cuando se seleccionan medicinas
$('.medicina-check').change(function() {
    verificarInteraccionesActuales();
});

function verificarInteraccionesActuales() {
    const medicinasSeleccionadas = [];
    $('.medicina-check:checked').each(function() {
        medicinasSeleccionadas.push($(this).val());
    });

    if (medicinasSeleccionadas.length > 0) {
        $.post('ajax/chequear_interacciones.php', 
            { medicinas: medicinasSeleccionadas }, 
            function(data) {
                mostrarInteracciones(data);
            }, 
            'json'
        );
    } else {
        $('#alertInteracciones').addClass('d-none');
    }
}

function mostrarInteracciones(interacciones) {
    const alerta = $('#alertInteracciones');
    const lista = $('#listaInteracciones');
    
    lista.empty();
    
    if (interacciones.length > 0) {
        interacciones.forEach(interaccion => {
            const severidadClass = interaccion.severidad === 'alta' ? 'danger' : 
                                 interaccion.severidad === 'media' ? 'warning' : 'success';
            
            lista.append(`
                <li class="interaction-${interaccion.severidad}">
                    <strong>${interaccion.medicamento_a} + ${interaccion.medicamento_b}</strong><br>
                    <span class="badge badge-${severidadClass}">${interaccion.severidad.toUpperCase()}</span>
                    - ${interaccion.nota || 'Interacción detectada'}
                </li>
            `);
        });
        alerta.removeClass('d-none');
    } else {
        alerta.addClass('d-none');
    }
}
</script>