<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$servername = "localhost"; 
require_once dirname(__DIR__, 2) . '/conn/connection.php';

// Verificar si se ha proporcionado un id_expendedora
if (!isset($_GET['id_expendedora'])) {
    header('Content-Type: application/json');
    die(json_encode(["error" => "id_expendedora no proporcionado."]));
}

$id_expendedora = $_GET['id_expendedora'];

$sql = "SELECT 
            id, 
            cierre_expendedora_id, 
            partial_fichas, 
            partial_dinero, 
            partial_p1, 
            partial_p2, 
            partial_p3, 
            partial_devolucion, 
            partial_normales, 
            partial_promocion, 
            partial_cambio, 
            employee_id, 
            created_at 
        FROM subcierres_expendedoras 
        WHERE cierre_expendedora_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    header('Content-Type: application/json');
    die(json_encode(["error" => "Prepare failed: " . $conn->error]));
}

$stmt->bind_param("s", $id_expendedora);

if (!$stmt->execute()) {
    header('Content-Type: application/json');
    die(json_encode(["error" => "Execute failed: " . $stmt->error]));
}

$result = $stmt->get_result();

$partial_reports = [];
while ($row = $result->fetch_assoc()) {
    $partial_reports[] = $row;
}

// Debug rápido
error_log("Subcierres para {$id_expendedora}: " . count($partial_reports));

$response = [
    "partial_reports" => $partial_reports,
    "total" => count($partial_reports)
];

header('Content-Type: application/json');
echo json_encode($response);

$stmt->close();
$conn->close();
?>
