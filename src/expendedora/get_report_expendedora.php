<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/conn/config.php';

if (!isset($_GET['device_id'])) {
    header('Content-Type: application/json');
    die(json_encode(["error" => "device_id no proporcionado."]));
}

$device_id = $_GET['device_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Resolver id_dispositivo desde codigo_hardware
$stmtDisp = $conn->prepare("SELECT id_dispositivo FROM dispositivos WHERE LOWER(codigo_hardware) = LOWER(:id) LIMIT 1");
$stmtDisp->execute([':id' => $device_id]);
$disp = $stmtDisp->fetch(PDO::FETCH_ASSOC);
if (!$disp) {
    header('Content-Type: application/json');
    echo json_encode(["reports" => []]);
    exit;
}

$id_dispositivo = (int)$disp['id_dispositivo'];

$sql = "SELECT fecha_registro, fichas, dinero FROM telemetria_expendedoras 
        WHERE id_dispositivo = :id ORDER BY fecha_registro DESC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
$stmt->bindValue(':id', $id_dispositivo, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$reports = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $reports[] = [
        'timestamp' => $row['fecha_registro'],
        'dato1'     => (int)$row['fichas'],
        'dato2'     => (float)$row['dinero']
    ];
}

header('Content-Type: application/json');
echo json_encode(["reports" => $reports]);
