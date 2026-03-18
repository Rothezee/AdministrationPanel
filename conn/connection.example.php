<?php
/**
 * @deprecated Usar conn/config.php (PDO). Mantenido por compatibilidad.
 * Copiar a connection.php y completar credenciales.
 */
$servername = "localhost";
$username = "root";
$password = "TU_PASSWORD";    // Cambiar
$dbname = "sistemadeadministracion";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
