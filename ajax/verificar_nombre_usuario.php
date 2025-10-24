<?php
// Responde solo texto
header('Content-Type: text/plain; charset=UTF-8');

include '../config/connection.php';

// Toma y limpia el usuario
$user = isset($_GET['user_name']) ? trim($_GET['user_name']) : '';
if ($user === '') { echo 0; exit; }

try {
  // Cuenta usuarios con ese nombre (consulta preparada)
  $sql = "SELECT COUNT(*) AS c
          FROM `usuarios`
          WHERE `usuario` = :u";
  $stmt = $con->prepare($sql);
  $stmt->bindParam(':u', $user, PDO::PARAM_STR);
  $stmt->execute();

  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  echo (int)$row['c']; // imprime solo el número
} catch (PDOException $ex) {
  // En error, no bloquear el flujo
  echo 0;
}
