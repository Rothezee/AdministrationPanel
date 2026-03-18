<?php
/**
 * Script para debuggear un pago específico
 * Útil para verificar por qué un pago no se procesó correctamente
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once 'database.php';
require_once 'mercadopago.php';

// ID del pago a debuggear (cámbialo por el que necesites)
$paymentId = isset($argv[1]) ? $argv[1] : '1324510238';

echo "🔍 DEBUGGEANDO PAGO: $paymentId\n";
echo str_repeat("=", 60) . "\n";

try {
    $db = new Database();
    $conn = $db->getConnection();
    $mp = new MercadoPagoHandler($conn);
    
    // 1. Verificar en base de datos local
    echo "1️⃣ Verificando en base de datos local...\n";
    $stmt = $conn->prepare("
        SELECT * FROM mercadopago_requests 
        WHERE payment_id = ? OR preference_id LIKE ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$paymentId, "%$paymentId%"]);
    $localRecords = $stmt->fetchAll();
    
    if ($localRecords) {
        echo "✅ Encontrados " . count($localRecords) . " registros locales:\n";
        foreach ($localRecords as $record) {
            echo "   - ID: {$record['id']}, Status: {$record['status']}, Máquina: {$record['machine_id']}, Monto: {$record['amount']}\n";
        }
    } else {
        echo "❌ No se encontraron registros locales\n";
    }
    
    echo "\n";
    
    // 2. Consultar a MercadoPago
    echo "2️⃣ Consultando a MercadoPago API...\n";
    $paymentInfo = $mp->getPaymentInfo($paymentId);
    
    if ($paymentInfo) {
        echo "✅ Información obtenida de MercadoPago:\n";
        echo "   - Status: " . ($paymentInfo['status'] ?? 'N/A') . "\n";
        echo "   - Monto: " . ($paymentInfo['transaction_amount'] ?? 'N/A') . "\n";
        echo "   - Máquina: " . ($paymentInfo['external_reference'] ?? 'N/A') . "\n";
        echo "   - Fecha: " . ($paymentInfo['date_created'] ?? 'N/A') . "\n";
        echo "   - Método: " . ($paymentInfo['payment_method_id'] ?? 'N/A') . "\n";
        
        // Mostrar datos completos si es necesario
        if (isset($argv[2]) && $argv[2] === '--full') {
            echo "\n📋 Datos completos:\n";
            echo json_encode($paymentInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    } else {
        echo "❌ No se pudo obtener información de MercadoPago\n";
        echo "   Posibles causas:\n";
        echo "   - Access Token incorrecto\n";
        echo "   - Payment ID no existe\n";
        echo "   - Problema de conectividad\n";
    }
    
    echo "\n";
    
    // 3. Verificar logs de webhook
    echo "3️⃣ Verificando logs de webhook...\n";
    $stmt = $conn->prepare("
        SELECT * FROM mercadopago_webhook_logs 
        WHERE payment_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$paymentId]);
    $webhookLogs = $stmt->fetchAll();
    
    if ($webhookLogs) {
        echo "✅ Encontrados " . count($webhookLogs) . " logs de webhook:\n";
        foreach ($webhookLogs as $log) {
            echo "   - Evento: {$log['event_type']}, Status: {$log['status']}, Procesado: " . ($log['processed'] ? 'Sí' : 'No') . "\n";
            if ($log['error_message']) {
                echo "     Error: {$log['error_message']}\n";
            }
        }
    } else {
        echo "❌ No se encontraron logs de webhook\n";
    }
    
    echo "\n";
    
    // 4. Verificar transacciones
    echo "4️⃣ Verificando transacciones de crédito...\n";
    $stmt = $conn->prepare("
        SELECT * FROM mercadopago_transactions 
        WHERE machine_id IN (
            SELECT machine_id FROM mercadopago_requests WHERE payment_id = ?
        )
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$paymentId]);
    $transactions = $stmt->fetchAll();
    
    if ($transactions) {
        echo "✅ Encontradas " . count($transactions) . " transacciones:\n";
        foreach ($transactions as $tx) {
            echo "   - Máquina: {$tx['machine_id']}, Monto: {$tx['amount']}, Status: {$tx['status']}\n";
            if ($tx['details']) {
                echo "     Detalles: {$tx['details']}\n";
            }
        }
    } else {
        echo "❌ No se encontraron transacciones de crédito\n";
    }
    
    echo "\n";
    
    // 5. Intentar procesar manualmente si está aprobado
    if ($paymentInfo && ($paymentInfo['status'] ?? '') === 'approved') {
        echo "5️⃣ El pago está aprobado. ¿Intentar procesar manualmente? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim($line) === 'y' || trim($line) === 'Y') {
            echo "🔄 Procesando pago manualmente...\n";
            $machineId = $paymentInfo['external_reference'];
            $amount = $paymentInfo['transaction_amount'];
            
            $result = $mp->processApprovedPayment($paymentId, $machineId, $amount);
            
            echo "Resultado: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    echo "\n✅ DEBUG COMPLETADO\n";
    
} catch (Exception $e) {
    echo "❌ Error durante el debug: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n💡 Comandos útiles:\n";
echo "php debug_payment.php [PAYMENT_ID] --full  # Ver datos completos\n";
echo "php test_webhook.php                       # Probar webhook\n";