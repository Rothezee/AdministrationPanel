<?php
// Redirige al reporte unificado (report.php maneja todos los tipos)
$deviceId = $_GET['device_id'] ?? null;
if ($deviceId) {
    header('Location: ../administracion/report.php?device_id=' . urlencode($deviceId) . '&tipo=expendedora');
} else {
    header('Location: ../administracion/dashboard.php');
}
exit;
