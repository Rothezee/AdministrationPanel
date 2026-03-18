<?php
// Lógica principal del endpoint de reportes crudos.

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__, 2) . '/error_log.txt');

date_default_timezone_set('America/Argentina/Buenos_Aires');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin', '*');

require_once dirname(__DIR__, 2) . '/conn/config.php';

try {
    $idDispositivo = isset($_GET['id_dispositivo']) ? (int)$_GET['id_dispositivo'] : null;
    $tipoMaquina   = isset($_GET['tipo_maquina'])   ? (int)$_GET['tipo_maquina']   : null;
    $fechaDesde    = isset($_GET['fecha_desde'])    ? $_GET['fecha_desde']          : null;
    $fechaHasta    = isset($_GET['fecha_hasta'])    ? $_GET['fecha_hasta']          : null;

    if (!$idDispositivo || !$tipoMaquina) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Faltan parámetros obligatorios: id_dispositivo y tipo_maquina'
        ]);
        exit();
    }

    $tabla = null;
    $columnas = [];

    switch ($tipoMaquina) {
        case 1:
            $tabla    = 'telemetria_expendedoras';
            $columnas = ['fichas', 'dinero'];
            break;
        case 2:
            $tabla    = 'telemetria_gruas';
            $columnas = ['pago', 'coin', 'premios', 'banco'];
            break;
        case 3:
            $tabla    = 'telemetria_ticketeras';
            $columnas = ['fichas', 'tickets'];
            break;
        case 4:
            $tabla    = 'telemetria_videojuegos';
            $columnas = ['fichas'];
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'tipo_maquina no reconocido'
            ]);
            exit();
    }

    $sql = "SELECT id_lectura, id_dispositivo, " . implode(',', $columnas) . ", fecha_registro 
            FROM {$tabla}
            WHERE id_dispositivo = :id_dispositivo";

    $params = [':id_dispositivo' => $idDispositivo];

    if ($fechaDesde) {
        $sql .= " AND fecha_registro >= :fecha_desde";
        $params[':fecha_desde'] = $fechaDesde . ' 00:00:00';
    }
    if ($fechaHasta) {
        $sql .= " AND fecha_registro <= :fecha_hasta";
        $params[':fecha_hasta'] = $fechaHasta . ' 23:59:59';
    }

    $sql .= " ORDER BY fecha_registro ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $reports = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $reports[] = $row;
    }

    echo json_encode([
        'success' => true,
        'reports' => $reports
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno: ' . $e->getMessage()
    ]);
}

