<?php
require_once dirname(__DIR__, 2) . '/config.php';

$machineId = $_GET['machine_id'] ?? 'N/A';
$amount = $_GET['amount'] ?? '0';
$error = $_GET['error'] ?? 'Error desconocido';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Fallido - Máquinas Bonus</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #f44336, #d32f2f);
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
        .error-icon {
            font-size: 64px;
            color: #f44336;
            margin-bottom: 20px;
        }
        h1 {
            color: #c62828;
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
        .btn {
            background: #f44336;
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
            background: #d32f2f;
        }
        .btn-secondary {
            background: #2196F3;
        }
        .btn-secondary:hover {
            background: #1976D2;
        }
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #f44336;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">❌</div>
        <h1>Pago No Procesado</h1>
        <p>Hubo un problema al procesar tu pago.</p>
        
        <div class="error-message">
            <strong>Motivo:</strong> El pago fue rechazado o cancelado.
        </div>
        
        <div class="details">
            <div class="detail-row">
                <span><strong>Máquina:</strong></span>
                <span><?php echo htmlspecialchars($machineId); ?></span>
            </div>
            <div class="detail-row">
                <span><strong>Monto:</strong></span>
                <span>$<?php echo number_format($amount, 2); ?></span>
            </div>
            <div class="detail-row">
                <span><strong>Fecha:</strong></span>
                <span><?php echo date('d/m/Y H:i:s'); ?></span>
            </div>
        </div>

        <p><strong>¿Qué puedes hacer?</strong></p>
        <ul style="text-align: left; max-width: 300px; margin: 0 auto;">
            <li>Verificar los datos de tu tarjeta</li>
            <li>Intentar con otro método de pago</li>
            <li>Contactar a tu banco si el problema persiste</li>
        </ul>

        <div style="margin-top: 30px;">
            <a href="javascript:history.back()" class="btn">Intentar Nuevamente</a>
            <a href="/src/administracion/dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
        </div>
    </div>
</body>
</html>