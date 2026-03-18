<?php
// get_all_devices.php
// Devuelve dispositivos que han reportado (tabla dispositivos, ultimo_heartbeat)

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__, 2) . '/error_log.txt');

date_default_timezone_set('America/Argentina/Buenos_Aires');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Connection: close');

require_once dirname(__DIR__, 2) . '/conn/config.php';

$stmt = $conn->query("SELECT codigo_hardware, ultimo_heartbeat, estado_conexion FROM dispositivos ORDER BY codigo_hardware ASC");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed']);
    exit;
}

$devices = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $devices[] = [
        'device_id'      => $row['codigo_hardware'],
        'last_heartbeat' => $row['ultimo_heartbeat'],
        'estado'         => $row['estado_conexion']
    ];
}

echo json_encode(['devices' => $devices]);