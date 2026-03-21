<?php
declare(strict_types=1);
/**
 * Publica configuración de juego para grúa (ESP32 Gigga) por MQTT.
 * Tópico: maquinas/{codigo_hardware}/config (MQTT_CONFIG_SUBTOPIC, default "config")
 */
session_start();
if (empty($_SESSION['username'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/conn/config.php';
// Desde src/administracion → src/devices (evita confusiones con rutas)
$mqtPub = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'devices' . DIRECTORY_SEPARATOR . 'MqttDevicePublisher.php';
if (!is_readable($mqtPub)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'No se encuentra MqttDevicePublisher.php. Ruta esperada: ' . $mqtPub,
    ]);
    exit;
}
require_once $mqtPub;

/**
 * La columna dispositivos.tipo_maquina es ENUM('Expendedora','Grua',...).
 * PDO suele devolver la etiqueta 'Grua', no el índice 2; (int)'Grua' === 0 y fallaba el chequeo.
 */
function dispositivo_es_grua($tipoMaquina): bool
{
    if ($tipoMaquina === null || $tipoMaquina === '') {
        return false;
    }
    $map = ['Expendedora' => 1, 'Grua' => 2, 'Ticketera' => 3, 'Videojuego' => 4];
    $key = (string) $tipoMaquina;
    if (isset($map[$key])) {
        return $map[$key] === 2;
    }
    foreach ($map as $nombre => $num) {
        if (strcasecmp($key, $nombre) === 0) {
            return $num === 2;
        }
    }
    return is_numeric($tipoMaquina) && (int) $tipoMaquina === 2;
}

/** @return array{ok:bool, error?:string, pago?:int, t_agarre?:int, t_fuerte?:int, fuerza?:int} */
function validate_grua_params(array $in): array
{
    $pago = isset($in['pago']) ? (int) $in['pago'] : null;
    $tAgarre = isset($in['t_agarre']) ? (int) $in['t_agarre'] : null;
    $tFuerte = isset($in['t_fuerte']) ? (int) $in['t_fuerte'] : null;
    $fuerza = isset($in['fuerza']) ? (int) $in['fuerza'] : null;

    if ($pago === null || $tAgarre === null || $tFuerte === null || $fuerza === null) {
        return ['ok' => false, 'error' => 'Faltan campos: pago, t_agarre, t_fuerte, fuerza'];
    }
    if ($pago < 1 || $pago > 99) {
        return ['ok' => false, 'error' => 'pago debe estar entre 1 y 99'];
    }
    if ($tAgarre < 500 || $tAgarre > 5000) {
        return ['ok' => false, 'error' => 't_agarre debe estar entre 500 y 5000 (ms)'];
    }
    if ($tFuerte < 0 || $tFuerte > 5000) {
        return ['ok' => false, 'error' => 't_fuerte debe estar entre 0 y 5000 (ms)'];
    }
    if ($fuerza < 5 || $fuerza > 101) {
        return ['ok' => false, 'error' => 'fuerza debe estar entre 5 y 101'];
    }

    return [
        'ok' => true,
        'pago' => $pago,
        't_agarre' => $tAgarre,
        't_fuerte' => $tFuerte,
        'fuerza' => $fuerza,
    ];
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $deviceId = isset($input['device_id']) ? trim((string) $input['device_id']) : '';

    if ($deviceId === '') {
        echo json_encode(['success' => false, 'error' => 'device_id requerido']);
        exit;
    }

    $v = validate_grua_params($input);
    if (!$v['ok']) {
        echo json_encode(['success' => false, 'error' => $v['error']]);
        exit;
    }

    $idAdmin = isset($_SESSION['id_admin']) ? (int) $_SESSION['id_admin'] : 0;
    if ($idAdmin <= 0) {
        $stmt = $conn->prepare('SELECT id_admin FROM usuarios_admin WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $_SESSION['username']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sesión inválida']);
            exit;
        }
        $idAdmin = (int) $row['id_admin'];
    }

    $stmt = $conn->prepare('
        SELECT id_dispositivo, codigo_hardware, tipo_maquina
        FROM dispositivos
        WHERE LOWER(codigo_hardware) = LOWER(:id) AND id_admin = :admin
        LIMIT 1
    ');
    $stmt->execute([':id' => $deviceId, ':admin' => $idAdmin]);
    $disp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$disp) {
        echo json_encode(['success' => false, 'error' => 'Dispositivo no encontrado o no pertenece a tu cuenta']);
        exit;
    }

    if (!dispositivo_es_grua($disp['tipo_maquina'] ?? null)) {
        echo json_encode(['success' => false, 'error' => 'Solo aplica a máquinas tipo grúa']);
        exit;
    }

    $codigo = $disp['codigo_hardware'];
    $subtopic = trim((string) (getenv('MQTT_CONFIG_SUBTOPIC') ?: 'config'), '/');
    if ($subtopic === '') {
        $subtopic = 'config';
    }

    $payload = [
        'cmd' => 'set_grua_params',
        'ts' => time(),
        'pago' => $v['pago'],
        't_agarre' => $v['t_agarre'],
        't_fuerte' => $v['t_fuerte'],
        'fuerza' => $v['fuerza'],
    ];

    $result = MqttDevicePublisher::publishJson($codigo, $subtopic, $payload);

    if (!$result['success']) {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Error MQTT',
            'topic' => $result['topic'] ?? null,
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Configuración enviada por MQTT. La grúa debe estar conectada al broker para aplicarla.',
        'topic' => $result['topic'] ?? null,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
