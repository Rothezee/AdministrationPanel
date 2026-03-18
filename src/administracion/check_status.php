<?php
/**
 * check_status.php
 * Devuelve online/offline según ultimo_heartbeat.
 * Con heartbeat cada 10 min: si ultimo_heartbeat > 11 min → offline.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__, 2) . '/conn/config.php';

$deviceId = $_GET['device_id'] ?? null;
if (!$deviceId) {
    echo json_encode(['error' => 'device_id requerido', 'status' => 'offline']);
    exit;
}

// Margen: 11 min (heartbeat cada 10 min en ESP32)
define('HEARTBEAT_UMBRAL_MIN', 11);

$stmt = $conn->prepare("
    SELECT ultimo_heartbeat,
           TIMESTAMPDIFF(MINUTE, ultimo_heartbeat, NOW()) AS minutos_desde
    FROM dispositivos
    WHERE codigo_hardware = :codigo
    LIMIT 1
");
$stmt->execute([':codigo' => $deviceId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || $row['ultimo_heartbeat'] === null) {
    echo json_encode(['status' => 'offline']);
    exit;
}

$minutosDesde = (int)($row['minutos_desde'] ?? 999);
$status = ($minutosDesde <= HEARTBEAT_UMBRAL_MIN) ? 'online' : 'offline';

// Opcional: actualizar estado en BD si está desactualizado
if ($status === 'offline') {
    $upd = $conn->prepare("UPDATE dispositivos SET estado_conexion = 'offline' WHERE codigo_hardware = :codigo");
    $upd->execute([':codigo' => $deviceId]);
}

echo json_encode(['status' => $status]);
