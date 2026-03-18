<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once 'database.php';
require_once 'mercadopago.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Soportar GET, POST JSON y POST form-urlencoded
$input = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Intenta decodificar JSON
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    // Si no es JSON válido, usar $_POST
    if (!is_array($input)) {
        $input = $_POST;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $input = $_GET;
} else {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Validar parámetros
if (!isset($input['amount']) || !isset($input['machine_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan parámetros requeridos']);
    exit;
}

$amount = floatval($input['amount']);
$machineId = $input['machine_id'];
$description = $input['description'] ?? '';

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'El monto debe ser mayor a 0']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Ajustar zona horaria en DB y PHP
    $conn->exec("SET time_zone = '-03:00'");
    date_default_timezone_set('America/Argentina/Buenos_Aires');

    $mp = new MercadoPagoHandler($conn);
    $result = $mp->createPayment($amount, $machineId, $description);

    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'details' => $e->getMessage()
    ]);
}
