<?php
require_once dirname(__DIR__, 2) . '/config.php';

$machineId = $_GET['machine_id'] ?? 'N/A';
$amount = $_GET['amount'] ?? '0';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Pendiente - Máquinas Bonus</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #FF9800, #F57C00);
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
        .pending-icon {
            font-size: 64px;
            color: #FF9800;
            margin-bottom: 20px;
        }
        h1 {
            color: #E65100;
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
            background: #FF9800;
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
            background: #F57C00;
        }
        .btn-secondary {
            background: #2196F3;
        }
        .btn-secondary:hover {
            background: #1976D2;
        }
        .pending-message {
            background: #FFF8E1;
            color: #E65100;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #FF9800;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #FF9800;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="pending-icon">⏳</div>
        <h1>Pago Pendiente</h1>
        <p>Tu pago está siendo procesado.</p>
        
        <div class="pending-message">
            <div class="loading"></div>
            <strong>Estamos verificando tu pago...</strong><br>
            Esto puede tomar unos minutos dependiendo del método de pago utilizado.
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

        <p><strong>¿Qué sucede ahora?</strong></p>
        <ul style="text-align: left; max-width: 350px; margin: 0 auto;">
            <li>Recibirás una notificación cuando se confirme el pago</li>
            <li>El crédito se agregará automáticamente a la máquina</li>
            <li>Puedes cerrar esta ventana y volver más tarde</li>
        </ul>

        <div style="margin-top: 30px;">
            <a href="/src/administracion/dashboard.php" class="btn">Volver al Dashboard</a>
            <a href="#" onclick="location.reload()" class="btn btn-secondary">Actualizar Estado</a>
        </div>
    </div>

    <script>
        // Auto-refresh cada 30 segundos para verificar el estado
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>