<?php
/**
 * delete_device.php
 * Elimina una máquina (dispositivo) y todos sus reportes asociados.
 * Las FK tienen ON DELETE CASCADE, así que se borran telemetría, cierres, etc.
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
    $deviceId = $input['device_id'] ?? $_POST['device_id'] ?? null;

    if (!$deviceId || trim($deviceId) === '') {
        echo json_encode(['success' => false, 'error' => 'device_id requerido']);
        exit;
    }

    $deviceId = trim($deviceId);

    $stmt = $conn->prepare("SELECT id_dispositivo, codigo_hardware FROM dispositivos WHERE LOWER(codigo_hardware) = LOWER(:id) LIMIT 1");
    $stmt->execute([':id' => $deviceId]);
    $disp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$disp) {
        echo json_encode(['success' => false, 'error' => 'Dispositivo no encontrado']);
        exit;
    }

    $idDispositivo = (int)$disp['id_dispositivo'];
    $codigo = $disp['codigo_hardware'];

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("DELETE FROM dispositivos WHERE id_dispositivo = :id");
        $stmt->execute([':id' => $idDispositivo]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('No se pudo eliminar el dispositivo');
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "Máquina {$codigo} eliminada correctamente junto con todos sus reportes.",
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
