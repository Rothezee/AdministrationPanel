<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/config.php';
require_once 'database.php';

header('Content-Type: application/json');

$machine_id = $_GET['machine_id'] ?? null;
$ip = $_GET['ip'] ?? null;

if (!$machine_id || !$ip) {
    echo json_encode(['success' => false, 'error' => 'Faltan parámetros']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        INSERT INTO machine_ips (device_id, ip_address)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE ip_address = VALUES(ip_address)
    ");
    $stmt->execute([$machine_id, $ip]);



    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'ip' => $ip]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Máquina no encontrada']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
