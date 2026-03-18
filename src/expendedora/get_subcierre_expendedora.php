<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/conn/config.php';

if (!isset($_GET['id_expendedora'])) {
    header('Content-Type: application/json');
    die(json_encode(["error" => "id_expendedora no proporcionado."]));
}

$id_expendedora = $_GET['id_expendedora'];

// Resolver id_dispositivo desde codigo_hardware
$stmtDisp = $conn->prepare("SELECT id_dispositivo FROM dispositivos WHERE LOWER(codigo_hardware) = LOWER(:id) LIMIT 1");
$stmtDisp->execute([':id' => $id_expendedora]);
$disp = $stmtDisp->fetch(PDO::FETCH_ASSOC);
if (!$disp) {
    header('Content-Type: application/json');
    echo json_encode(["partial_reports" => [], "total" => 0]);
    exit;
}

$id_dispositivo = (int)$disp['id_dispositivo'];

$sql = "SELECT cp.fichas_totales, cp.dinero, cp.p1, cp.p2, cp.p3, cp.fichas_promo, cp.fichas_devolucion, cp.fichas_cambio, cp.fecha_apertura_turno, cp.fecha_cierre_turno, c.usuario_cajero,
               COALESCE(DATE(cd.fecha_apertura), DATE(cp.fecha_apertura_turno)) as fecha_dia
        FROM cierres_parciales cp
        LEFT JOIN cierres_diarios cd ON cp.id_cierre_diario = cd.id_cierre_diario
        LEFT JOIN cajeros c ON cp.id_cajero = c.id_cajero
        WHERE cp.id_dispositivo = :id
        ORDER BY cp.fecha_cierre_turno DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $id_dispositivo]);

$partial_reports = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $fichas_totales = (int)$row['fichas_totales'];
    $fichas_promo   = (int)$row['fichas_promo'];
    $fichas_devolucion = (int)$row['fichas_devolucion'];
    $fichas_cambio  = (int)$row['fichas_cambio'];
    $partial_normales = max(0, $fichas_totales - $fichas_promo - $fichas_devolucion - $fichas_cambio);

    // fecha_dia: día comercial (del cierre padre) o día que inició el turno; nunca usar fecha_cierre_turno
    $fecha_dia = $row['fecha_dia'] ?? null;
    if (empty($fecha_dia) && !empty($row['fecha_apertura_turno'])) {
        $fecha_dia = date('Y-m-d', strtotime($row['fecha_apertura_turno']));
    }

    $partial_reports[] = [
        'fecha_dia'           => $fecha_dia,
        'fecha_apertura_turno' => $row['fecha_apertura_turno'],
        'created_at'          => $row['fecha_cierre_turno'],
        'partial_fichas'  => $fichas_totales,
        'partial_dinero'  => (float)$row['dinero'],
        'partial_p1'      => (int)$row['p1'],
        'partial_p2'      => (int)$row['p2'],
        'partial_p3'      => (int)$row['p3'],
        'partial_devolucion' => $fichas_devolucion,
        'partial_normales'   => $partial_normales,
        'partial_promocion' => $fichas_promo,
        'partial_cambio'    => $fichas_cambio,
        'employee_id'     => $row['usuario_cajero'] ?? null
    ];
}

header('Content-Type: application/json');
echo json_encode([
    "partial_reports" => $partial_reports,
    "total"           => count($partial_reports)
]);
