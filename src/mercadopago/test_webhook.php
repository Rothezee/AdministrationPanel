<?php
/**
 * Script para probar el webhook localmente
 * Simula las notificaciones que envía MercadoPago
 */

require_once dirname(__DIR__, 2) . '/config.php';

// URL del webhook
$webhookUrl = 'http://localhost/esp32_project/Backend/mercadopago/webhook.php';

// Datos de prueba para simular una notificación de payment
$paymentNotification = [
    "action" => "payment.created",
    "api_version" => "v1",
    "data" => [
        "id" => "test_payment_" . time()
    ],
    "date_created" => date('c'),
    "id" => rand(100000, 999999),
    "live_mode" => false,
    "type" => "payment",
    "user_id" => "test_user"
];

// Datos de prueba para simular una notificación de merchant_order
$merchantOrderNotification = [
    "resource" => "https://api.mercadolibre.com/merchant_orders/test_order_" . time(),
    "topic" => "merchant_order"
];

function sendTestNotification($url, $data, $type) {
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Enviando notificación de prueba: $type\n";
    echo "Datos: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    echo str_repeat("-", 50) . "\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: MercadoPago-Test-Webhook'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "Respuesta HTTP: $httpCode\n";
    if ($error) {
        echo "Error cURL: $error\n";
    } else {
        echo "Respuesta: $response\n";
    }
    echo str_repeat("=", 50) . "\n";
}

// Ejecutar pruebas
echo "🧪 INICIANDO PRUEBAS DEL WEBHOOK\n";

// Prueba 1: Notificación de payment
sendTestNotification($webhookUrl, $paymentNotification, 'Payment');

// Prueba 2: Notificación de merchant_order
sendTestNotification($webhookUrl, $merchantOrderNotification, 'Merchant Order');

// Prueba 3: Notificación con GET parameters (formato alternativo)
$getUrl = $webhookUrl . '?type=payment&data.id=test_get_' . time();
echo "\n" . str_repeat("=", 50) . "\n";
echo "Enviando notificación GET: $getUrl\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $getUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Respuesta HTTP: $httpCode\n";
echo "Respuesta: $response\n";
echo str_repeat("=", 50) . "\n";

echo "\n✅ PRUEBAS COMPLETADAS\n";
echo "Revisa el archivo webhook_log.txt para ver los logs detallados\n";