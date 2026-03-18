<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once 'database.php';

// Configurar zona horaria
date_default_timezone_set(Config::TIMEZONE);

$machineId = $_GET['machine_id'] ?? 'N/A';
$amount = $_GET['amount'] ?? '0';
$paymentId = $_GET['payment_id'] ?? null;
$status = $_GET['status'] ?? null;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Exitoso - Máquinas Bonus</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .success-icon {
            font-size: 64px;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        h1 {
            color: #2E7D32;
            margin-bottom: 20px;
        }
        .details {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #2E7D32;
        }
        .btn {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #45a049;
        }
        .btn-secondary {
            background: #2196F3;
        }
        .btn-secondary:hover {
            background: #1976D2;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #4CAF50;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .status-message {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
        }
        .status-processing {
            background: #FFF3CD;
            color: #856404;
            border: 1px solid #FFEAA7;
        }
        .status-success {
            background: #D4EDDA;
            color: #155724;
            border: 1px solid #C3E6CB;
        }
        .status-error {
            background: #F8D7DA;
            color: #721C24;
            border: 1px solid #F5C6CB;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h1>¡Pago Exitoso!</h1>
        <p>Tu pago ha sido procesado correctamente.</p>
        
        <div class="details">
            <div class="detail-row">
                <span><strong>Máquina:</strong></span>
                <span><?php echo htmlspecialchars($machineId); ?></span>
            </div>
            <div class="detail-row">
                <span><strong>Monto:</strong></span>
                <span class="amount">$<?php echo number_format($amount, 2); ?></span>
            </div>
            <div class="detail-row">
                <span><strong>Fecha:</strong></span>
                <span><?php echo date('d/m/Y H:i:s'); ?></span>
            </div>
            <?php if ($paymentId): ?>
            <div class="detail-row">
                <span><strong>ID de Pago:</strong></span>
                <span><?php echo htmlspecialchars($paymentId); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div id="status-message" class="status-message status-processing">
            <div class="loading"></div>
            Procesando crédito para la máquina...
        </div>

        <div style="margin-top: 30px;">
            <a href="/src/administracion/dashboard.php" class="btn">Volver al Dashboard</a>
            <a href="#" onclick="window.close()" class="btn btn-secondary">Cerrar</a>
        </div>
    </div>

    <script>
        // Verificar el estado del crédito cada 2 segundos
        let checkCount = 0;
        const maxChecks = 15; // Máximo 30 segundos

        function checkCreditStatus() {
            if (checkCount >= maxChecks) {
                document.getElementById('status-message').innerHTML = 
                    '<strong>⚠️ El crédito está siendo procesado</strong><br>Puede tomar unos minutos adicionales.';
                document.getElementById('status-message').className = 'status-message status-processing';
                return;
            }

            fetch(`/esp32_project/Backend/mercadopago/check_credit_status.php?machine_id=<?php echo urlencode($machineId); ?>&payment_id=<?php echo urlencode($paymentId); ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('status-message').innerHTML = 
                            '<strong>✅ ¡Crédito agregado exitosamente!</strong><br>Ya puedes usar la máquina.';
                        document.getElementById('status-message').className = 'status-message status-success';
                    } else if (data.error) {
                        document.getElementById('status-message').innerHTML = 
                            '<strong>❌ Error procesando crédito</strong><br>' + data.error;
                        document.getElementById('status-message').className = 'status-message status-error';
                    } else {
                        checkCount++;
                        setTimeout(checkCreditStatus, 2000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    checkCount++;
                    if (checkCount < maxChecks) {
                        setTimeout(checkCreditStatus, 2000);
                    }
                });
        }

        // Iniciar verificación después de 2 segundos
        setTimeout(checkCreditStatus, 2000);
    </script>
</body>
</html>