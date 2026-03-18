<?php
// ===== CONFIGURACIÓN Y HEADERS =====
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

date_default_timezone_set('America/Argentina/Buenos_Aires');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ===== DEFINICIÓN DE CONSTANTES (Compatible con PHP < 8.1) =====
abstract class Action {
    const Heartbeat = 1;
    const Telemetry = 2;
}

abstract class TipoMaquina {
    const Expendedora = 1;
    const Grua = 2;
    const Ticketera = 3;
    const Videojuego = 4;
}

// ===== CONEXIÓN A LA BD (usa la misma BD del panel) =====
require_once dirname(__DIR__, 2) . '/conn/config.php';

// ===== LEER Y VALIDAR JSON =====
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "JSON inválido"]);
    exit();
}

// Validar que existan los campos
if (!isset($data['action'], $data['dni_admin'], $data['codigo_hardware'], $data['tipo_maquina'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Faltan datos obligatorios"]);
    exit();
}

$action = (int)$data['action'];
$tipo_maquina = (int)$data['tipo_maquina'];
$dni_admin = $data['dni_admin'];
$codigo_hardware = $data['codigo_hardware'];

// Si el ESP32 mandó un número que no existe en el Enum (ej: action = 9)
$valid_actions = [Action::Heartbeat, Action::Telemetry];
$valid_types = [TipoMaquina::Expendedora, TipoMaquina::Grua, TipoMaquina::Ticketera, TipoMaquina::Videojuego];

if (!in_array($action, $valid_actions) || !in_array($tipo_maquina, $valid_types)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Action o Tipo de Máquina no reconocidos"]);
    exit();
}

try {
    $conn->beginTransaction();

    // ===== 1. VALIDAR CLIENTE =====
    $stmtAdmin = $conn->prepare("SELECT id_admin FROM usuarios_admin WHERE dni = :dni");
    $stmtAdmin->execute([':dni' => $dni_admin]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        throw new Exception("ACCESO DENEGADO: DNI no registrado.", 403);
    }
    $id_admin_real = $admin['id_admin'];

    // ===== 2. RESOLVER DISPOSITIVO =====
    $stmtDisp = $conn->prepare("SELECT id_dispositivo, id_admin FROM dispositivos WHERE codigo_hardware = :codigo");
    $stmtDisp->execute([':codigo' => $codigo_hardware]);
    $dispositivo = $stmtDisp->fetch(PDO::FETCH_ASSOC);

    if ($dispositivo) {
        $id_dispositivo = $dispositivo['id_dispositivo'];
        if ($dispositivo['id_admin'] != $id_admin_real) {
             throw new Exception("CONFLICTO: Equipo asignado a otro administrador.", 403);
        }
    } else {
        // Al guardar en BD, le pasamos el "value" del Enum (el número entero)
        $stmtInsert = $conn->prepare("
            INSERT INTO dispositivos (id_admin, codigo_hardware, tipo_maquina, estado_conexion, ultimo_heartbeat) 
            VALUES (:admin, :codigo, :tipo, 'online', NOW())
        ");
        $stmtInsert->execute([
            ':admin' => $id_admin_real,
            ':codigo' => $codigo_hardware,
            ':tipo' => $tipo_maquina
        ]);
        $id_dispositivo = $conn->lastInsertId();
    }

    // ===== 3. ACTUALIZAR HEARTBEAT =====
    $stmtHb = $conn->prepare("
        UPDATE dispositivos 
        SET estado_conexion = 'online', ultimo_heartbeat = NOW() 
        WHERE id_dispositivo = :id
    ");
    $stmtHb->execute([':id' => $id_dispositivo]);

    // ===== 4. PROCESAR TELEMETRÍA USANDO MATCH/SWITCH CON ENUMS =====
    if ($action === Action::Telemetry && isset($data['payload'])) {
        $payload = $data['payload'];
        
        switch ($tipo_maquina) {
            case TipoMaquina::Grua:
                $stmtTel = $conn->prepare("INSERT INTO telemetria_gruas (id_dispositivo, pago, coin, premios, banco) VALUES (?, ?, ?, ?, ?)");
                $stmtTel->execute([$id_dispositivo, $payload['pago'], $payload['coin'], $payload['premios'], $payload['banco']]);
                break;
                
            case TipoMaquina::Expendedora:
                $stmtTel = $conn->prepare("INSERT INTO telemetria_expendedoras (id_dispositivo, fichas, dinero) VALUES (?, ?, ?)");
                $stmtTel->execute([$id_dispositivo, $payload['fichas'], $payload['dinero']]);
                break;
                
            case TipoMaquina::Ticketera:
                $stmtTel = $conn->prepare("INSERT INTO telemetria_ticketeras (id_dispositivo, fichas, tickets) VALUES (?, ?, ?)");
                $stmtTel->execute([$id_dispositivo, $payload['fichas'], $payload['tickets']]);
                break;
                
            case TipoMaquina::Videojuego:
                $stmtTel = $conn->prepare("INSERT INTO telemetria_videojuegos (id_dispositivo, fichas) VALUES (?, ?)");
                $stmtTel->execute([$id_dispositivo, $payload['fichas']]);
                break;
        }
    }

    $conn->commit();
    
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "OK"]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $code = $e->getCode();
    $codigo_http = (is_int($code) && $code >= 100 && $code <= 599) ? $code : 500;
    http_response_code($codigo_http);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>