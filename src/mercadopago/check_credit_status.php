<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once 'database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$machineId = $_GET['machine_id'] ?? null;
$paymentId = $_GET['payment_id'] ?? null;

if (!$machineId) {
    echo json_encode(['error' => 'machine_id requerido']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Buscar el estado del pago y transacción
    $stmt = $conn->prepare("
        SELECT 
            mr.status as payment_status,
            mt.status as transaction_status,
            mt.details,
            mr.amount,
            mr.updated_at
        FROM mercadopago_requests mr
        LEFT JOIN mercadopago_transactions mt ON mr.machine_id = mt.machine_id 
            AND mt.created_at >= mr.created_at
        WHERE mr.machine_id = ? 
        " . ($paymentId ? "AND mr.payment_id = ?" : "") . "
        ORDER BY mr.created_at DESC, mt.created_at DESC
        LIMIT 1
    ");
    
    if ($paymentId) {
        $stmt->execute([$machineId, $paymentId]);
    } else {
        $stmt->execute([$machineId]);
    }
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode(['error' => 'No se encontró información del pago']);
        exit;
    }

    $response = [
        'payment_status' => $result['payment_status'],
        'transaction_status' => $result['transaction_status'],
        'amount' => $result['amount'],
        'updated_at' => $result['updated_at']
    ];

    // Determinar el estado final
    if ($result['payment_status'] === 'approved' && $result['transaction_status'] === 'success') {
        $response['success'] = true;
        $response['message'] = 'Crédito agregado exitosamente';
    } elseif ($result['payment_status'] === 'approved' && $result['transaction_status'] === 'failed') {
        $response['success'] = false;
        $response['error'] = 'Pago aprobado pero error enviando crédito: ' . $result['details'];
    } elseif ($result['payment_status'] === 'rejected') {
        $response['success'] = false;
        $response['error'] = 'Pago rechazado';
    } else {
        $response['success'] = null; // Aún pendiente
        $response['message'] = 'Procesando...';
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}