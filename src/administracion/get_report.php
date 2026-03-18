<?php
/**
 * get_report.php
 * Devuelve conteo de reportes para preview de borrado.
 * Parámetros: device_id, fechaFin (YYYY-MM-DD)
 */
session_start();
if (empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/conn/config.php';

$deviceId = $_GET['device_id'] ?? null;
$fechaFin = $_GET['fechaFin'] ?? $_GET['fecha_hasta'] ?? null;

if (!$deviceId || !$fechaFin || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
    echo json_encode(['reports' => [], 'count' => 0]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id_dispositivo, tipo_maquina FROM dispositivos WHERE LOWER(codigo_hardware) = LOWER(:id) LIMIT 1");
    $stmt->execute([':id' => trim($deviceId)]);
    $disp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$disp) {
        echo json_encode(['reports' => [], 'count' => 0]);
        exit;
    }

    $idDispositivo = (int)$disp['id_dispositivo'];
    $tipoMap = ['Expendedora' => 1, 'Grua' => 2, 'Ticketera' => 3, 'Videojuego' => 4];
    $tipo = isset($tipoMap[$disp['tipo_maquina']]) ? $tipoMap[$disp['tipo_maquina']] : 1;

    $tablas = [
        1 => 'telemetria_expendedoras',
        2 => 'telemetria_gruas',
        3 => 'telemetria_ticketeras',
        4 => 'telemetria_videojuegos',
    ];
    $tabla = $tablas[$tipo] ?? 'telemetria_expendedoras';
    $fechaLimite = $fechaFin . ' 23:59:59';

    $stmt = $conn->prepare("SELECT COUNT(*) as n FROM {$tabla} WHERE id_dispositivo = :id AND fecha_registro <= :fecha");
    $stmt->execute([':id' => $idDispositivo, ':fecha' => $fechaLimite]);
    $count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['n'];

    if ($tipo === 1) {
        $stmt = $conn->prepare("SELECT COUNT(*) as n FROM cierres_diarios WHERE id_dispositivo = :id AND fecha_cierre <= :fecha");
        $stmt->execute([':id' => $idDispositivo, ':fecha' => $fechaLimite]);
        $count += (int)$stmt->fetch(PDO::FETCH_ASSOC)['n'];

        $stmt = $conn->prepare("SELECT COUNT(*) as n FROM cierres_parciales WHERE id_dispositivo = :id AND fecha_cierre_turno <= :fecha");
        $stmt->execute([':id' => $idDispositivo, ':fecha' => $fechaLimite]);
        $count += (int)$stmt->fetch(PDO::FETCH_ASSOC)['n'];
    }

    echo json_encode(['reports' => array_fill(0, $count, (object)[]), 'count' => $count]);

} catch (Exception $e) {
    echo json_encode(['reports' => [], 'count' => 0, 'error' => $e->getMessage()]);
}
