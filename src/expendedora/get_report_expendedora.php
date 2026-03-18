<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/conn/connection.php';

// Verificar si se ha proporcionado un device_id
if (!isset($_GET['device_id'])) {
    header('Content-Type: application/json');
    die(json_encode(["error" => "device_id no proporcionado."]));
}

$device_id = $_GET['device_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
 
// Consulta para obtener los datos de la máquina de tickets
$sql = "SELECT id, device_id, dato1, dato2, timestamp FROM datos WHERE device_id = ? ORDER BY timestamp DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    header('Content-Type: application/json');
    die(json_encode(["error" => "Prepare failed: " . $conn->error]));
}
$stmt->bind_param("sii", $device_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$reports = [];
while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}

$response = [
    "reports" => $reports
];

header('Content-Type: application/json');
echo json_encode($response);
$stmt->close();
$conn->close();
?>
