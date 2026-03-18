<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once 'database.php';
require_once 'mercadopago.php';

// Configurar logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/webhook_errors.log');

header('Content-Type: application/json');

function logWebhook($label, $data = null) {
    $timestamp = date("Y-m-d H:i:s");
    $entry = "[$timestamp] $label";
    if ($data !== null) {
        $entry .= " | " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    $entry .= "\n" . str_repeat("-", 80) . "\n";
    file_put_contents(__DIR__ . '/webhook_log.txt', $entry, FILE_APPEND | LOCK_EX);
}

// =====================================================
// Capturar método y raw input
// =====================================================
$method    = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$raw_input = file_get_contents('php://input');
$headers   = getallheaders();

logWebhook("Webhook recibido", [
    'method'  => $method,
    'headers' => $headers,
    'raw'     => $raw_input,
    'server'  => [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]
]);

// =====================================================
// Si no es POST o no hay body -> MODO DEBUG
// =====================================================
if ($method !== 'POST' || trim($raw_input) === '') {
    $fake = [
        "type" => "payment",
        "data" => ["id" => "fake_payment_".time()],
        "debug" => true
    ];
    logWebhook("⚠️ DEBUG: Simulación de notificación", $fake);
    http_response_code(200);
    echo json_encode(['status' => 'debug', 'message' => 'Simulación de webhook', 'data' => $fake]);
    exit;
}

// =====================================================
// Decodificar JSON
// =====================================================
$input = json_decode($raw_input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logWebhook("ERROR: JSON inválido", ['error' => json_last_error_msg(), 'raw' => $raw_input]);
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => 'JSON inválido']);
    exit;
}

// =====================================================
// Manejar diferentes tipos de notificaciones
// =====================================================
try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->exec("SET time_zone = '-03:00'");
    date_default_timezone_set(Config::TIMEZONE);

    $mp = new MercadoPagoHandler($conn);

    // Guardar log del webhook
    $stmt = $conn->prepare("
        INSERT INTO mercadopago_webhook_logs (payment_id, event_type, status, raw_data, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");

    // Caso 1: Notificación de payment
    if (isset($input['type']) && $input['type'] === 'payment' && isset($input['data']['id'])) {
        $paymentId = $input['data']['id'];
        $action = $input['action'] ?? 'unknown';
        
        logWebhook("📧 Notificación de payment", ['paymentId' => $paymentId, 'action' => $action]);
        
        $stmt->execute([$paymentId, 'payment', $action, $raw_input]);
        
        // Intentar procesar el pago con reintentos
        $result = processPaymentWithRetries($mp, $paymentId, 3);
        
        logWebhook("🔄 Resultado procesamiento payment", $result);
        
        http_response_code(200);
        echo json_encode($result);
        exit;
    }

    // Caso 2: Notificación de merchant_order
    if (isset($input['topic']) && $input['topic'] === 'merchant_order' && isset($input['resource'])) {
        $resourceUrl = $input['resource'];
        $merchantOrderId = basename($resourceUrl);
        
        logWebhook("📧 Notificación de merchant_order", ['merchantOrderId' => $merchantOrderId, 'resource' => $resourceUrl]);
        
        $stmt->execute([$merchantOrderId, 'merchant_order', 'received', $raw_input]);
        
        // Procesar merchant order
        $result = processMerchantOrder($mp, $resourceUrl);
        
        logWebhook("🔄 Resultado procesamiento merchant_order", $result);
        
        http_response_code(200);
        echo json_encode($result);
        exit;
    }

    // Caso 3: Notificación con parámetros GET (formato alternativo)
    if (isset($_GET['type']) && $_GET['type'] === 'payment' && isset($_GET['data.id'])) {
        $paymentId = $_GET['data.id'];
        
        logWebhook("📧 Notificación GET payment", ['paymentId' => $paymentId]);
        
        $stmt->execute([$paymentId, 'payment_get', 'received', json_encode($_GET)]);
        
        $result = processPaymentWithRetries($mp, $paymentId, 3);
        
        logWebhook("🔄 Resultado procesamiento GET payment", $result);
        
        http_response_code(200);
        echo json_encode($result);
        exit;
    }

    // Notificación no reconocida
    logWebhook("⚠️ Notificación no reconocida", $input);
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'message' => 'Tipo de notificación no reconocida']);

} catch (Exception $e) {
    logWebhook("❌ Excepción en webhook", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// =====================================================
// Funciones auxiliares
// =====================================================

function processPaymentWithRetries($mp, $paymentId, $maxRetries = 3) {
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        logWebhook("🔄 Intento $attempt de $maxRetries para payment $paymentId");
        
        $paymentInfo = $mp->getPaymentInfo($paymentId);
        
        if ($paymentInfo) {
            $status = $paymentInfo['status'] ?? 'unknown';
            $machineId = $paymentInfo['external_reference'] ?? null;
            $amount = $paymentInfo['transaction_amount'] ?? 0;
            
            logWebhook("✅ Info del pago obtenida", [
                'paymentId' => $paymentId,
                'status' => $status,
                'machineId' => $machineId,
                'amount' => $amount,
                'attempt' => $attempt
            ]);
            
            if ($status === 'approved' && $machineId && $amount > 0) {
                return $mp->processApprovedPayment($paymentId, $machineId, $amount);
            } else {
                return ['status' => $status, 'message' => "Pago en estado: $status"];
            }
        }
        
        if ($attempt < $maxRetries) {
            logWebhook("⏳ Esperando antes del siguiente intento...");
            sleep(2); // Esperar 2 segundos antes del siguiente intento
        }
    }
    
    return ['status' => 'error', 'message' => "No se pudo obtener información del pago después de $maxRetries intentos"];
}

function processMerchantOrder($mp, $resourceUrl) {
    // Obtener información de la merchant order
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $resourceUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . Config::MP_ACCESS_TOKEN,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['status' => 'error', 'message' => "Error obteniendo merchant order: HTTP $httpCode"];
    }
    
    $orderData = json_decode($response, true);
    if (!$orderData) {
        return ['status' => 'error', 'message' => 'Respuesta inválida de merchant order'];
    }
    
    logWebhook("📦 Datos de merchant order", $orderData);
    
    // Buscar pagos aprobados en la orden
    $payments = $orderData['payments'] ?? [];
    foreach ($payments as $payment) {
        if ($payment['status'] === 'approved') {
            $paymentId = $payment['id'];
            $machineId = $orderData['external_reference'] ?? null;
            $amount = $payment['transaction_amount'] ?? 0;
            
            if ($machineId && $amount > 0) {
                logWebhook("💰 Procesando pago aprobado desde merchant order", [
                    'paymentId' => $paymentId,
                    'machineId' => $machineId,
                    'amount' => $amount
                ]);
                
                return $mp->processApprovedPayment($paymentId, $machineId, $amount);
            }
        }
    }
    
    return ['status' => 'pending', 'message' => 'No hay pagos aprobados en la orden'];
}