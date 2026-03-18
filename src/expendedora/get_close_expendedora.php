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
    echo json_encode(["reports" => []]);
    exit;
}

$id_dispositivo = (int)$disp['id_dispositivo'];

$sql = "SELECT fichas_totales, dinero, p1, p2, p3, fichas_promo, fichas_devolucion, fichas_cambio, fecha_apertura, fecha_cierre 
        FROM cierres_diarios WHERE id_dispositivo = :id ORDER BY fecha_cierre DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $id_dispositivo]);

$reports = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $fichas_totales = (int)$row['fichas_totales'];
    $fichas_promo   = (int)$row['fichas_promo'];
    $fichas_devolucion = (int)$row['fichas_devolucion'];
    $fichas_cambio  = (int)$row['fichas_cambio'];
    $fichas_normales = max(0, $fichas_totales - $fichas_promo - $fichas_devolucion - $fichas_cambio);

    $fecha_apertura = $row['fecha_apertura'] ?? null;
    $fecha_dia = $fecha_apertura ? date('Y-m-d', strtotime($fecha_apertura)) : null;

    $reports[] = [
        'id_expendedora'     => $id_expendedora,
        'fecha_dia'          => $fecha_dia,
        'fecha_apertura'     => $fecha_apertura,
        'timestamp'          => $row['fecha_cierre'],
        'fichas'            => $fichas_totales,
        'dinero'            => (float)$row['dinero'],
        'p1'                => (int)$row['p1'],
        'p2'                => (int)$row['p2'],
        'p3'                => (int)$row['p3'],
        'fichas_devolucion'  => $fichas_devolucion,
        'fichas_normales'    => $fichas_normales,
        'fichas_promocion'   => $fichas_promo,
        'fichas_cambio'      => $fichas_cambio
    ];
}

header('Content-Type: application/json');
echo json_encode(["reports" => $reports]);
