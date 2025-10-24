function guardarAsignacion() {
    const pacienteId = $('#selectPaciente').val();
    const enfermedadSelect = $('#selectEnfermedad').val();
    const enfermedad = enfermedadSelect === 'otro' ? $('#otraEnfermedad').val() : $('#selectEnfermedad option:selected').text();
    const motivo = $('#motivoPrescripcion').val();
    const dosis = $('#dosis').val();
    const frecuencia = $('#frecuencia').val();
    const duracion = $('#duracion').val();
    
    const medicinas = [];
    $('.medicina-check:checked').each(function() {
        medicinas.push($(this).val());
    });

    // Validaciones
    if (!pacienteId || !enfermedad || !motivo || !dosis || !frecuencia || medicinas.length === 0) {
        alert('Por favor, complete todos los campos obligatorios (*)');
        return;
    }

    if (enfermedadSelect === 'otro' && !enfermedad) {
        alert('Por favor, especifique la enfermedad');
        return;
    }

    // Preparar datos para enviar
    const datos = {
        paciente_id: pacienteId,
        enfermedad: enfermedad,
        motivo: motivo,
        dosis: dosis,
        frecuencia: frecuencia,
        duracion: duracion,
        medicinas: medicinas
    };

    // Enviar datos al servidor
    $.ajax({
        url: 'guardar_medicina_paciente.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(datos),
        success: function(response) {
            if (response.success) {
                alert('¡Medicinas asignadas correctamente!');
                $('#modalAsignarMedicinas').modal('hide');
                // Recargar la página para ver los cambios
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error de conexión. Intente nuevamente.');
        }
    });
}