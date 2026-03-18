<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/conn/connection.php';

// Verificar si se ha proporcionado un id_expendedora
if (!isset($_GET['id_expendedora'])) {
    header('Content-Type: application/json');
    die(json_encode(["error" => "id_expendedora no proporcionado."]));
}

$id_expendedora = $_GET['id_expendedora'];

// Consulta para obtener los cierres diarios
$sql = "SELECT id_expendedora, fichas, dinero, p1, p2, p3, fichas_devolucion, fichas_normales, fichas_promocion, fichas_cambio, timestamp FROM cierres_expendedoras WHERE id_expendedora = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    header('Content-Type: application/json');
    die(json_encode(["error" => "Prepare failed: " . $conn->error]));
}
$stmt->bind_param("s", $id_expendedora);
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
