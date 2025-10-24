<?php
// Helpers de auditoría (opcionales). Es seguro incluirlo en cualquier página.

if (!function_exists('audit_row')) {
  /**
   * Devuelve el registro completo para auditoría (o un mínimo con el ID).
   */
  function audit_row($con, string $tabla, string $pkCol, $id) {
    if (!($con instanceof PDO)) return [$pkCol => $id];
    $sql = "SELECT * FROM `$tabla` WHERE `$pkCol` = :id LIMIT 1";
    $st = $con->prepare($sql);
    $st->execute([':id'=>$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: [$pkCol => $id];
  }
}
