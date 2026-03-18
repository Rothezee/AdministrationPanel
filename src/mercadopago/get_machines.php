<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once 'database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener todas las máquinas con su estado
    $stmt = $conn->prepare("
        SELECT 
            mi.device_id,
            mi.name,
            mi.location,
            mi.ip_address,
            mi.last_seen,
            d.last_heartbeat,
            CASE 
                WHEN d.last_heartbeat IS NOT NULL 
                AND TIMESTAMPDIFF(MINUTE, d.last_heartbeat, NOW()) <= 5 
                THEN 1 
                ELSE 0 
            END as online
        FROM machine_ips mi
        LEFT JOIN devices d ON mi.device_id = d.device_id
        ORDER BY mi.name, mi.device_id
    ");
    
    $stmt->execute();
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear datos
    foreach ($machines as &$machine) {
        $machine['online'] = (bool)$machine['online'];
        $machine['last_seen_formatted'] = $machine['last_seen'] ? 
            date('d/m/Y H:i:s', strtotime($machine['last_seen'])) : 'Nunca';
        $machine['last_heartbeat_formatted'] = $machine['last_heartbeat'] ? 
            date('d/m/Y H:i:s', strtotime($machine['last_heartbeat'])) : 'Nunca';
    }

    echo json_encode([
        'success' => true,
        'machines' => $machines,
        'total' => count($machines)
    ]);

} catch (Exception $e) {
    error_log("Error getting machines: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}