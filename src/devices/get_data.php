<?php
/**
 * get_data.php
 * Devuelve la última telemetría de un dispositivo para el dashboard.
 * Formato: dato1, dato2, dato3, dato4, dato5 según tipo de máquina.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__, 2) . '/conn/config.php';

$deviceId = $_GET['device_id'] ?? null;
if (!$deviceId || trim($deviceId) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'device_id requerido']);
    exit;
}

$deviceId = trim($deviceId);

try {
    // Resolver dispositivo por codigo_hardware (case-insensitive)
    $stmt = $conn->prepare("
        SELECT id_dispositivo, tipo_maquina
        FROM dispositivos
        WHERE LOWER(codigo_hardware) = LOWER(:codigo)
        LIMIT 1
    ");
    $stmt->execute([':codigo' => $deviceId]);
    $dispositivo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dispositivo) {
        echo json_encode(['error' => 'Dispositivo no encontrado']);
        exit;
    }

    $idDispositivo = (int)$dispositivo['id_dispositivo'];
    $tipoRaw = $dispositivo['tipo_maquina'];
    // BD usa ENUM('Expendedora','Grua','Ticketera','Videojuego'); aceptar string o int
    $tipoMap = ['Expendedora' => 1, 'Grua' => 2, 'Ticketera' => 3, 'Videojuego' => 4];
    $tipoMaquina = isset($tipoMap[$tipoRaw]) ? $tipoMap[$tipoRaw] : (int)$tipoRaw;

    $dato1 = $dato2 = $dato3 = $dato4 = $dato5 = null;

    switch ($tipoMaquina) {
        case 1: // Expendedora: fichas, dinero
            $stmt = $conn->prepare("
                SELECT fichas, dinero FROM telemetria_expendedoras
                WHERE id_dispositivo = :id
                ORDER BY fecha_registro DESC LIMIT 1
            ");
            $stmt->execute([':id' => $idDispositivo]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dato1 = (int)$row['fichas'];
                $dato2 = (int)$row['dinero'];
            }
            break;

        case 2: // Grúa: pago, coin, premios, banco
            $stmt = $conn->prepare("
                SELECT pago, coin, premios, banco FROM telemetria_gruas
                WHERE id_dispositivo = :id
                ORDER BY fecha_registro DESC LIMIT 1
            ");
            $stmt->execute([':id' => $idDispositivo]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dato1 = (int)$row['pago'];
                $dato2 = (int)$row['coin'];
                $dato3 = (int)$row['premios'];
                $dato4 = (int)$row['banco'];
            }
            break;

        case 3: // Ticketera: fichas, tickets
            $stmt = $conn->prepare("
                SELECT fichas, tickets FROM telemetria_ticketeras
                WHERE id_dispositivo = :id
                ORDER BY fecha_registro DESC LIMIT 1
            ");
            $stmt->execute([':id' => $idDispositivo]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dato2 = (int)$row['fichas'];   // coin
                $dato5 = (int)$row['tickets'];
            }
            break;

        case 4: // Videojuego: fichas
            $stmt = $conn->prepare("
                SELECT fichas FROM telemetria_videojuegos
                WHERE id_dispositivo = :id
                ORDER BY fecha_registro DESC LIMIT 1
            ");
            $stmt->execute([':id' => $idDispositivo]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dato2 = (int)$row['fichas'];   // coin
            }
            break;

        default:
            echo json_encode(['error' => 'Tipo de máquina no reconocido']);
            exit;
    }

    $out = [
        'dato1' => $dato1,
        'dato2' => $dato2,
        'dato3' => $dato3,
        'dato4' => $dato4,
        'dato5' => $dato5,
    ];

    echo json_encode($out);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
