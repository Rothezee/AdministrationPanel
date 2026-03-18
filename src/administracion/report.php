<?php
// Reporte unificado para todos los tipos: grúa, videojuego, ticketera, expendedora
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/conn/config.php';

$deviceId = $_GET['device_id'] ?? null;
$tipo = $_GET['tipo'] ?? 'maquina';
$tiposValidos = ['maquina', 'videojuego', 'ticket', 'expendedora'];
if (!in_array($tipo, $tiposValidos)) $tipo = 'maquina';

if (!$deviceId) {
    http_response_code(400);
    echo "Falta el parámetro device_id";
    exit();
}

$idDispositivo = null;
$stmt = $conn->prepare("SELECT id_dispositivo FROM dispositivos WHERE LOWER(codigo_hardware) = LOWER(:id) LIMIT 1");
$stmt->execute([':id' => $deviceId]);
$disp = $stmt->fetch();
if ($disp) {
    $idDispositivo = (int)$disp['id_dispositivo'];
}

$headers = [
    'maquina' => [
        'reporte' => ['Fecha y hora', 'Pesos', 'Coin', 'Premios', 'Banco'],
        'cierres' => ['Fecha', 'Pesos', 'Coin', 'Premios', 'Banco'],
    ],
    'videojuego' => [
        'reporte' => ['Fecha y hora', 'Fichas'],
        'cierres' => ['Fecha', 'Coin'],
    ],
    'ticket' => [
        'reporte' => ['Fecha y hora', 'Fichas', 'Tickets'],
        'cierres' => ['Fecha', 'Coin', 'Premios'],
    ],
    'expendedora' => [
        'reporte' => ['Fecha y hora', 'Fichas', 'Dinero'],
        'cierres' => ['Fecha', 'Fichas', 'Dinero', 'P1', 'P2', 'P3', 'Fichas Devolución', 'Fichas Normales', 'Fichas Promocion', 'Extender'],
        'cierresSemanales' => ['Fichas', 'Dinero', 'P1', 'P2', 'P3'],
    ],
];
$h = $headers[$tipo] ?? $headers['maquina'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <title>Reporte <?php echo htmlspecialchars(ucfirst($tipo), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($deviceId, ENT_QUOTES, 'UTF-8'); ?></title>
</head>
<body>
<script>
window.REPORT_CONFIG = { deviceId: <?php echo json_encode($deviceId); ?>, idDispositivo: <?php echo $idDispositivo ? (int)$idDispositivo : 'null'; ?>, tipo: <?php echo json_encode($tipo); ?> };
</script>
<header>
    <nav class="navbar">
        <div class="container_navbar">
            <div class="navbar-header">
                <button class="navbar-toggler" data-toggle="open-navbar1"><span></span><span></span><span></span></button>
            </div>
            <div class="navbar-menu" id="open-navbar1">
                <ul class="navbar-nav">
                    <li><a href="dashboard.php">← Dashboard</a></li>
                    <li><a href="#reportes">Reportes</a></li>
                    <li><a href="#diarios">Cierres Diarios</a></li>
                    <li><a href="#semanales">Cierres Semanales</a></li>
                    <li><a href="#mensuales">Cierres Mensuales</a></li>
                    <li><a href="#graficas">Gráficas</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>
<main>
    <h1 id="machine_name" class="page-title" style="text-align:center;margin:1rem 0;">Máquina</h1>

    <section id="reportes" class="seccion active">
        <h2>Reportes crudos</h2>
        <div class="table-container">
            <table id="report_table">
                <thead>
                    <tr>
                        <?php foreach ($h['reporte'] as $th): ?><th><?php echo htmlspecialchars($th); ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <section id="diarios" class="seccion">
        <h2>Cierres Diarios</h2>
        <div class="table-container reportsContainer">
            <table id="tabla-diarios">
                <thead>
                    <tr>
                        <?php foreach ($h['cierres'] as $th): ?><th><?php echo htmlspecialchars($th); ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <section id="semanales" class="seccion">
        <h2>Cierres Semanales</h2>
        <p>Seleccioná el inicio de la semana:</p>
        <input type="text" id="selector-inicio-semana" placeholder="YYYY-MM-DD" style="max-width:180px;">
        <div class="table-container" style="margin-top:1rem;">
            <table id="tabla-semanales">
                <thead>
                    <tr>
                        <th>Semana</th>
                        <?php $colsSem = $h['cierresSemanales'] ?? array_slice($h['cierres'], 1); foreach ($colsSem as $th): ?><th><?php echo htmlspecialchars($th); ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <section id="mensuales" class="seccion">
        <h2>Cierres Mensuales</h2>
        <p>Seleccioná el mes:</p>
        <input type="text" id="selector-inicio-mes" placeholder="Mes / Año" style="max-width:180px;">
        <div class="table-container" style="margin-top:1rem;">
            <table id="tabla-mensuales">
                <thead>
                    <tr>
                        <th>Período</th>
                        <?php $colsMes = $h['cierresSemanales'] ?? array_slice($h['cierres'], 1); foreach ($colsMes as $th): ?><th><?php echo htmlspecialchars($th); ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <section id="graficas" class="seccion">
        <h2>Gráficas comparativas</h2>
        <p style="font-size:0.85rem;color:var(--text-muted);margin-top:0.5rem;">Coin y Premios (referencia para reponer peluches)</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-top:1rem;">
            <div>
                <h3>Coin y Premios diarios</h3>
                <canvas id="grafica-ganancias-diarias"></canvas>
            </div>
            <div>
                <h3>Coin y Premios semanales</h3>
                <canvas id="grafica-ganancias-semanales"></canvas>
            </div>
            <div>
                <h3>Coin y Premios mensuales</h3>
                <canvas id="grafica-ganancias-mensuales"></canvas>
            </div>
            <div>
                <h3>Comparativa Coin vs Premios</h3>
                <canvas id="grafica-comparativa"></canvas>
            </div>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/monthSelect.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../../assets/js/navbar.js"></script>
<script src="../../assets/js/report.js"></script>
</body>
</html>
