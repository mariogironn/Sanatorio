<?php
/**
 * Helpers comunes (seguros para incrustar en HTML)
 * - Todas las salidas están escapadas con htmlspecialchars.
 * - En caso de error de DB, se devuelve una opción indicándolo (sin traces).
 */

/**
 * Genera opciones <option> para Género.
 *
 * @param string $gender        Valor seleccionado (Masculino/Femenino/Otro)
 * @param string $placeholder   Texto de la primera opción
 * @return string               HTML <option>...
 */
function getGender(string $gender = '', string $placeholder = 'Selecciona Género'): string
{
  $opts = ['Masculino','Femenino','Otro'];
  $html = '<option value="">' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '</option>';

  foreach ($opts as $opt) {
    $sel = ($gender === $opt) ? ' selected="selected"' : '';
    $html .= '<option value="' . htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
          .  htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') . '</option>';
  }
  return $html;
}

/**
 * Genera opciones <option> para Medicamentos.
 *
 * @param PDO   $con
 * @param int   $id_medicamento  ID seleccionado (opcional)
 * @param bool  $soloActivos     Si tu tabla tiene columna `estado`, puedes pasar true para filtrar (opcional)
 * @return string                HTML <option>...
 */
function getMedicamentos(PDO $con, int $id_medicamento = 0, bool $soloActivos = false): string
{
  // Ajusta el WHERE si manejas estado en tu DB (por defecto no se usa para no romper nada)
  $sql = "SELECT `id`, `nombre_medicamento` FROM `medicamentos` "
       . ($soloActivos ? "WHERE `estado` = 1 " : "")
       . "ORDER BY `nombre_medicamento` ASC";

  try {
    $stmt = $con->prepare($sql);
    $stmt->execute();
  } catch (PDOException $ex) {
    return '<option value="">(Error cargando medicinas)</option>';
  }

  $html = '<option value="">' . htmlspecialchars('Selecciona Medicina', ENT_QUOTES, 'UTF-8') . '</option>';
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $id   = (int)$row['id'];
    $name = htmlspecialchars((string)$row['nombre_medicamento'], ENT_QUOTES, 'UTF-8');
    $sel  = ($id_medicamento === $id) ? ' selected="selected"' : '';
    $html .= '<option value="' . $id . '"' . $sel . '>' . $name . '</option>';
  }
  return $html;
}

/**
 * Genera opciones <option> para Pacientes.
 *
 * @param PDO $con
 * @param int $idSeleccionado  ID seleccionado (opcional)
 * @return string              HTML <option>...
 */
function getPacientes(PDO $con, int $idSeleccionado = 0): string
{
  $sql = "SELECT `id_paciente`, `nombre`, `telefono`
          FROM `pacientes`
          ORDER BY `nombre` ASC";
  try {
    $stmt = $con->prepare($sql);
    $stmt->execute();
  } catch (PDOException $ex) {
    return '<option value="">(Error cargando pacientes)</option>';
  }

  $html = '<option value="">' . htmlspecialchars('Selecciona Paciente', ENT_QUOTES, 'UTF-8') . '</option>';
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $id   = (int)$row['id_paciente'];
    $nom  = htmlspecialchars((string)$row['nombre'],   ENT_QUOTES, 'UTF-8');
    $tel  = htmlspecialchars((string)($row['telefono'] ?? ''), ENT_QUOTES, 'UTF-8');
    $txt  = $tel !== '' ? ($nom . ' (' . $tel . ')') : $nom;
    $sel  = ($idSeleccionado === $id) ? ' selected="selected"' : '';
    $html .= '<option value="' . $id . '"' . $sel . '>' . $txt . '</option>';
  }
  return $html;
}

/**
 * Crea un bloque de input de fecha (HTML + icono) con ID único.
 *
 * @param string $label     Etiqueta a mostrar
 * @param string $dateId    ID del input/selector (debe ser único en la página)
 * @param bool   $required  Si el campo es requerido
 * @param string $placeholder Placeholder opcional (p.ej. "dd/mm/aaaa")
 * @return string           HTML del control
 */
function getDateTextBox(string $label, string $dateId, bool $required = true, string $placeholder = ''): string
{
  $req = $required ? ' required="required"' : '';
  $ph  = $placeholder !== '' ? ' placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '"' : '';
  $lbl = htmlspecialchars($label,  ENT_QUOTES, 'UTF-8');
  $id  = htmlspecialchars($dateId, ENT_QUOTES, 'UTF-8');

  return '<div class="col-lg-3 col-md-3 col-sm-4 col-xs-10">
    <div class="form-group">
      <label for="' . $id . '">' . $lbl . '</label>
      <div class="input-group rounded-0 date" data-target-input="nearest">
        <input type="text" class="form-control form-control-sm rounded-0 datetimepicker-input"
               data-toggle="datetimepicker" data-target="#' . $id . '"
               name="' . $id . '" id="' . $id . '"' . $req . $ph . ' autocomplete="off"/>
        <div class="input-group-append rounded-0" data-target="#' . $id . '" data-toggle="datetimepicker">
          <div class="input-group-text"><i class="fa fa-calendar"></i></div>
        </div>
      </div>
    </div>
  </div>';
}
