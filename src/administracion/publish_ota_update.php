<?php
declare(strict_types=1);
/**
 * Publica comando OTA por MQTT: maquinas/{id}/{MQTT_OTA_SUBTOPIC}
 * Body JSON: { "device_ids": ["Grua_1"], "branch": "main" }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/conn/config.php';
require_once __DIR__ . '/OtaManifestHelper.php';

$mqtPub = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'devices' . DIRECTORY_SEPARATOR . 'MqttDevicePublisher.php';
if (!is_readable($mqtPub)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se encuentra MqttDevicePublisher.php']);
    exit;
}
require_once $mqtPub;

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $deviceIds = $input['device_ids'] ?? null;
    $branch = isset($input['branch']) ? trim((string) $input['branch']) : '';

    if (!is_array($deviceIds) || $deviceIds === []) {
        echo json_encode(['success' => false, 'error' => 'device_ids debe ser un array no vacío']);
        exit;
    }
    if ($branch === '') {
        echo json_encode(['success' => false, 'error' => 'branch requerido']);
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

    $manifest = OtaManifestHelper::loadManifest();
    $allowed = OtaManifestHelper::allowedBranchIds($manifest);
    if (!in_array($branch, $allowed, true)) {
        echo json_encode(['success' => false, 'error' => 'Rama no permitida: ' . $branch]);
        exit;
    }

    $entry = OtaManifestHelper::branchEntry($manifest, $branch);

    $subtopic = trim((string) (getenv('MQTT_OTA_SUBTOPIC') ?: 'ota'), '/');
    if ($subtopic === '') {
        $subtopic = 'ota';
    }

    $secret = trim((string) (getenv('OTA_SHARED_SECRET') ?: ''));
    $results = [];

    foreach ($deviceIds as $rawId) {
        $deviceId = trim((string) $rawId);
        if ($deviceId === '') {
            $results[] = ['device_id' => $rawId, 'success' => false, 'error' => 'id vacío'];
            continue;
        }

        $stmt = $conn->prepare('
            SELECT codigo_hardware
            FROM dispositivos
            WHERE LOWER(codigo_hardware) = LOWER(:id) AND id_admin = :admin
            LIMIT 1
        ');
        $stmt->execute([':id' => $deviceId, ':admin' => $idAdmin]);
        $disp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$disp) {
            $results[] = ['device_id' => $deviceId, 'success' => false, 'error' => 'No encontrado o no es tuyo'];
            continue;
        }

        $codigo = $disp['codigo_hardware'];
        $payload = [
            'cmd' => 'ota_update',
            'ts' => time(),
            'url' => $entry['url'],
            'version' => $entry['version'],
            'sha256' => $entry['sha256'],
            'branch' => $branch,
        ];
        if ($secret !== '') {
            $payload['ota_secret'] = $secret;
        }

        $pub = MqttDevicePublisher::publishJson($codigo, $subtopic, $payload);
        $results[] = [
            'device_id' => $codigo,
            'success' => $pub['success'],
            'error' => $pub['error'] ?? null,
            'topic' => $pub['topic'] ?? null,
        ];
    }

    $ok = !in_array(false, array_column($results, 'success'), true);
    echo json_encode([
        'success' => $ok,
        'branch' => $branch,
        'version' => $entry['version'],
        'results' => $results,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
