<?php
/**
 * MQTT Listener — Escucha maquinas/* y reenvía al api_receptor.
 */
date_default_timezone_set('America/Argentina/Buenos_Aires');

/**
 * Formato ESP32 Gold Digger: { device_id, dato1, dato2, dato3, dato4 }
 * Formato ESP32 Heartbeat:   { device_id, status }
 */
set_time_limit(0); 
ignore_user_abort(true);

// DNI del admin por defecto para dispositivos MQTT (debe existir en usuarios_admin)
$MQTT_DEFAULT_DNI = getenv('MQTT_DEFAULT_DNI') ?: '00000000';

$lock_file = __DIR__ . '/mqtt_listener.lock';
$fp = fopen($lock_file, 'c');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    echo "[MQTT] Ya hay una instancia corriendo.\n";
    exit();
}

$is_cli = (php_sapi_name() === 'cli');

function debug_log($message, $also_echo = true) {
    global $is_cli;
    $log_entry = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
    file_put_contents(__DIR__ . '/log_mqtt.txt', $log_entry, FILE_APPEND);
    if ($also_echo && $is_cli) echo $log_entry;
}

// --- CARGA DE LIBRERÍAS ---
$autoloader = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    $autoloader = dirname(__DIR__, 2) . '/../esp32_project/vendor/autoload.php';
}
if (!file_exists($autoloader)) {
    debug_log("[ERROR] No se encuentra vendor/autoload.php. Ejecutá: composer require php-mqtt/client");
    exit(1);
}
require $autoloader;

use PhpMqtt\Client\MQTTClient;
use PhpMqtt\Client\ConnectionSettings;

// ===== CONFIGURACIÓN =====
$mqtt_server    = 'broker.emqx.io'; 
$mqtt_port      = 1883;

// URL del api_receptor (para curl). Ajustar si el panel está en otra ruta.
$backend_base = getenv('MQTT_BACKEND_URL') ?: 'http://localhost/AdministrationPanel';
$backend_url_api = rtrim($backend_base, '/') . '/src/devices/api_receptor.php';

// Tópico comodín (#). Escucha absolutamente todo lo que se publique bajo "maquinas/"
// Ya no hace falta separar datos y heartbeat en la suscripción.
$topic_general = 'maquinas/#'; 

function send_to_backend($url, $json_data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json_data)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Agregué el $response al log. Así podés ver si el api_receptor.php 
    // tira algún error (ej. "DNI no registrado") directamente en log_mqtt.txt
    debug_log("-> Envío a API | HTTP: $http_code | Resp: " . trim($response));
}

// Filtro para evitar duplicados (mismo mensaje en <3 seg). Heartbeats son idénticos cada 10 min, no deduplicar por contenido solo.
$last_payload = "";
$last_payload_time = 0;

// Extrae device_id del tópico: maquinas/ESP32_005/datos → ESP32_005
function device_id_from_topic($topic) {
    if (preg_match('#^maquinas/([^/]+)/#', $topic, $m)) return $m[1];
    return null;
}

// Transforma formato ESP32 Gold Digger al formato esperado por api_receptor
function transform_esp32_to_api($topic, $message, $default_dni) {
    $raw = json_decode($message, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;

    // device_id: payload primero, fallback desde tópico; aceptar deviceId, Device_ID, etc.
    $device_id = $raw['device_id'] ?? $raw['deviceId'] ?? $raw['Device_ID'] ?? null;
    if (!$device_id) $device_id = device_id_from_topic($topic);
    if (!$device_id) return null;

    $is_heartbeat = (strpos($topic, 'heartbeat') !== false);
    $tipo_maquina = 2; // Grúa por defecto (Gold Digger)

    if ($is_heartbeat) {
        return [
            'action' => 1,
            'dni_admin' => $default_dni,
            'codigo_hardware' => $device_id,
            'tipo_maquina' => $tipo_maquina
        ];
    }

    // Telemetría: dato1=PAGO, dato2=COIN, dato3=PREMIOS, dato4=BANK (aceptar varias variantes)
    $pago    = (int)($raw['dato1'] ?? $raw['pago'] ?? $raw['PAGO'] ?? 0);
    $coin    = (int)($raw['dato2'] ?? $raw['coin'] ?? $raw['COIN'] ?? 0);
    $premios = (int)($raw['dato3'] ?? $raw['premios'] ?? $raw['PREMIOS'] ?? 0);
    $banco   = (int)($raw['dato4'] ?? $raw['banco'] ?? $raw['BANK'] ?? 0);

    return [
        'action' => 2,
        'dni_admin' => $default_dni,
        'codigo_hardware' => $device_id,
        'tipo_maquina' => $tipo_maquina,
        'payload' => ['pago' => $pago, 'coin' => $coin, 'premios' => $premios, 'banco' => $banco]
    ];
}

$callback_general = function ($topic, $message) use ($backend_url_api, &$last_payload, &$last_payload_time, $MQTT_DEFAULT_DNI) {
    // Normalizar: string, trim, quitar BOM y null bytes (a veces llegan del broker/ESP)
    $message = trim((string) $message);
    if (substr($message, 0, 3) === "\xEF\xBB\xBF") $message = substr($message, 3);
    $message = preg_replace('/\x00/', '', $message);
    if ($message === '') return;

    // Dedup: solo saltar si el MISMO mensaje llegó hace menos de 3 seg (evita duplicados MQTT).
    // Los heartbeats son idénticos cada 10 min; antes se filtraban incorrectamente.
    $now = time();
    if ($message === $last_payload && ($now - $last_payload_time) < 3) return;
    $last_payload = $message;
    $last_payload_time = $now;

    // maquinas/status: ESP publica estado (online/offline o 1/0). No es telemetría, ignorar sin log.
    if ($topic === 'maquinas/status' && in_array($message, ['online', 'offline', '1', '0'], true)) {
        return;
    }

    debug_log("MQTT <- $topic");

    $raw = json_decode($message, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $err = json_last_error_msg();
        $preview = strlen($message) > 80 ? substr($message, 0, 80) . '...' : $message;
        debug_log("  [SKIP] JSON inválido ($err) | len=" . strlen($message) . " | raw: " . $preview);
        return;
    }

    // Si ya viene en formato API (action, codigo_hardware, etc.), reenviar tal cual
    if (isset($raw['action'], $raw['codigo_hardware'], $raw['dni_admin'], $raw['tipo_maquina'])) {
        send_to_backend($backend_url_api, $message);
        return;
    }

    // Formato legacy (device_id, dato1..4): transformar; device_id puede venir del tópico
    $api_payload = transform_esp32_to_api($topic, $message, $MQTT_DEFAULT_DNI);
    if (!$api_payload) {
        $preview = strlen($message) > 80 ? substr($message, 0, 80) . '...' : $message;
        debug_log("  [SKIP] Sin device_id ni formato API | raw: " . $preview);
        return;
    }

    send_to_backend($backend_url_api, json_encode($api_payload));
};

$run_forever = !in_array('-t', $GLOBALS['argv'] ?? []) && !in_array('--timed', $GLOBALS['argv'] ?? []);
$max_reconnect_attempts = 10;
$reconnect_delay = 5;

$run_loop = true;
$reconnect_count = 0;
while ($run_loop) {
    try {
        $mqtt_client_id = 'php_majo_listener_v3_' . uniqid();
        $mqtt = new MQTTClient($mqtt_server, $mqtt_port, $mqtt_client_id);
        debug_log("Conectando al broker " . $mqtt_server . ":" . $mqtt_port . "...");
        $settings = new ConnectionSettings(0, false, false, 10, 60, 10);
        $mqtt->connect(null, null, $settings, true);
        $reconnect_count = 0; // reset on success
        debug_log("Conectado. Suscrito a: $topic_general");
        debug_log("Enviando a: $backend_url_api");
        debug_log("DNI admin: $MQTT_DEFAULT_DNI | Ctrl+C para salir\n");

        $mqtt->subscribe($topic_general, $callback_general, 0);

        $start_time = time();
        $duration = $run_forever ? PHP_INT_MAX : 600;

        while (time() - $start_time < $duration) {
            $mqtt->loop(true);
            usleep(50000);
        }
        $run_loop = false; // timed mode: salir
    } catch (\Throwable $e) {
        debug_log("[ERROR] " . $e->getMessage());
        if (!$run_forever) {
            $run_loop = false;
            break;
        }
        $reconnect_count++;
        if ($reconnect_count >= $max_reconnect_attempts) {
            debug_log("[ERROR] Máximo de reconexiones alcanzado. Saliendo.");
            $run_loop = false;
            break;
        }
        debug_log("Reconectando en {$reconnect_delay}s (intento $reconnect_count/$max_reconnect_attempts)...");
        sleep($reconnect_delay);
    }
}

// Cleanup
flock($fp, LOCK_UN);
fclose($fp);
if (file_exists($lock_file)) unlink($lock_file);
debug_log("Ciclo terminado. Lock liberado.");
?>