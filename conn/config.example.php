<?php
// conn/config.example.php — Copiar a config.php y completar credenciales.
// Conexión PDO a la base de datos principal del panel.

ini_set('display_errors', 0);
error_reporting(E_ALL);
date_default_timezone_set('America/Argentina/Buenos_Aires');

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = 'TU_PASSWORD';      // Cambiar
$dbName = 'sistemadeadministracion';

try {
    $conn = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $conn->exec("SET time_zone = '-03:00'");
} catch (PDOException $e) {
    http_response_code(500);
    echo "Error de conexión a la base de datos.";
    exit();
}
