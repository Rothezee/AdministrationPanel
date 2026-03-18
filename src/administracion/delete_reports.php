<?php
/**
 * delete_reports.php
 * Elimina reportes hasta una fecha (inclusive).
 * Modos: global (todas las máquinas) o device (una máquina).
 */
session_start();
if (empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/conn/config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $mode = $input['mode'] ?? 'global';
    $fechaHasta = $input['fecha_hasta'] ?? null;
    $deviceId = $input['device_id'] ?? null;

    if (!$fechaHasta || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
        echo json_encode(['success' => false, 'error' => 'fecha_hasta inválida (formato YYYY-MM-DD)']);
        exit;
    }

    $fechaLimite = $fechaHasta . ' 23:59:59';
    $idDispositivo = null;

    if ($mode === 'device') {
        if (!$deviceId || trim($deviceId) === '') {
            echo json_encode(['success' => false, 'error' => 'device_id requerido en modo device']);
            exit;
        }
        $stmt = $conn->prepare("SELECT id_dispositivo FROM dispositivos WHERE LOWER(codigo_hardware) = LOWER(:id) LIMIT 1");
        $stmt->execute([':id' => trim($deviceId)]);
        $disp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$disp) {
            echo json_encode(['success' => false, 'error' => 'Dispositivo no encontrado']);
            exit;
        }
        $idDispositivo = (int)$disp['id_dispositivo'];
    }

    $totalDeleted = 0;
    $details = [];

    $telemetriaTables = [
        1 => 'telemetria_expendedoras',
        2 => 'telemetria_gruas',
        3 => 'telemetria_ticketeras',
        4 => 'telemetria_videojuegos',
    ];

    if ($mode === 'global') {
        foreach ($telemetriaTables as $tabla) {
            $stmt = $conn->prepare("DELETE FROM {$tabla} WHERE fecha_registro <= :fecha");
            $stmt->execute([':fecha' => $fechaLimite]);
            $n = $stmt->rowCount();
            $totalDeleted += $n;
            $details[$tabla] = $n;
        }

        $stmt = $conn->prepare("DELETE FROM cierres_diarios WHERE fecha_cierre <= :fecha");
        $stmt->execute([':fecha' => $fechaLimite]);
        $n = $stmt->rowCount();
        $totalDeleted += $n;
        $details['cierres_diarios'] = $n;

        $stmt = $conn->prepare("DELETE FROM cierres_parciales WHERE fecha_cierre_turno <= :fecha");
        $stmt->execute([':fecha' => $fechaLimite]);
        $n = $stmt->rowCount();
        $totalDeleted += $n;
        $details['cierres_parciales'] = $n;

    } else {
        $stmt = $conn->prepare("SELECT tipo_maquina FROM dispositivos WHERE id_dispositivo = :id LIMIT 1");
        $stmt->execute([':id' => $idDispositivo]);
        $d = $stmt->fetch(PDO::FETCH_ASSOC);
        $tipoMap = ['Expendedora' => 1, 'Grua' => 2, 'Ticketera' => 3, 'Videojuego' => 4];
        $tipo = isset($tipoMap[$d['tipo_maquina']]) ? $tipoMap[$d['tipo_maquina']] : 1;
        $tabla = $telemetriaTables[$tipo] ?? 'telemetria_expendedoras';

        $stmt = $conn->prepare("DELETE FROM {$tabla} WHERE id_dispositivo = :id AND fecha_registro <= :fecha");
        $stmt->execute([':id' => $idDispositivo, ':fecha' => $fechaLimite]);
        $n = $stmt->rowCount();
        $totalDeleted += $n;
        $details[$tabla] = $n;

        if ($tipo === 1) {
            $stmt = $conn->prepare("DELETE FROM cierres_diarios WHERE id_dispositivo = :id AND fecha_cierre <= :fecha");
            $stmt->execute([':id' => $idDispositivo, ':fecha' => $fechaLimite]);
            $n = $stmt->rowCount();
            $totalDeleted += $n;
            $details['cierres_diarios'] = $n;

            $stmt = $conn->prepare("DELETE FROM cierres_parciales WHERE id_dispositivo = :id AND fecha_cierre_turno <= :fecha");
            $stmt->execute([':id' => $idDispositivo, ':fecha' => $fechaLimite]);
            $n = $stmt->rowCount();
            $totalDeleted += $n;
            $details['cierres_parciales'] = $n;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Se eliminaron {$totalDeleted} registro(s) hasta {$fechaHasta}.",
        'total_deleted' => $totalDeleted,
        'details' => $details,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
