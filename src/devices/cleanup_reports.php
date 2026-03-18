<?php
/**
 * cleanup_reports.php
 *
 * Este script se encarga de eliminar los registros antiguos de las tablas de reportes
 * para mantener la base de datos optimizada.
 *
 * Tablas afectadas:
 * - datos: reportes generales de las máquinas.
 * - cierres_expendedoras: cierres diarios de las expendedoras.
 * - subcierres_expendedoras: subcierres (parciales) de las expendedoras.
 *
 * El script elimina registros con más de 3 meses de antigüedad.
 * Se recomienda ejecutar este script periódicamente a través de un Cron Job.
 */

// Usar el archivo de conexión principal del proyecto para consistencia.
require_once dirname(__DIR__, 2) . '/conn/connection.php';

// Determinar si el script se ejecuta desde la consola (CLI) o desde un navegador.
$isCLI = (php_sapi_name() === 'cli');

if (!$isCLI) {
    header('Content-Type: application/json');
}

try {
    // La variable $conn ya está disponible desde 'connection.php'
    // El archivo connection.php usa MySQLi, por lo que no se usa setAttribute (que es de PDO).
    // La conexión ya se verifica en connection.php.
    if ($conn->connect_error) {
        throw new Exception("Error de conexión a la base de datos: " . $conn->connect_error);
    }

    // Establecer el charset a utf8mb4 para esta operación específica.
    // Esto es crucial para que la comparación de fechas en la consulta DELETE funcione correctamente.
    $conn->set_charset("utf8mb4");
    // Fecha límite: todo lo anterior a esta fecha será eliminado (hace 3 meses)
    $cutoffDate = date('Y-m-d H:i:s', strtotime('-3 months'));

    $tablesToClean = [
        'datos' => 'timestamp',
        'cierres_expendedoras' => 'timestamp',
        'subcierres_expendedoras' => 'created_at'
    ];

    $results = [];
    $totalDeleted = 0;

    if ($isCLI) {
        echo "Iniciando limpieza de registros anteriores a: " . $cutoffDate . "\n";
    }

    foreach ($tablesToClean as $table => $dateColumn) {
        $sql = "DELETE FROM {$table} WHERE {$dateColumn} < ?";
        $stmt = $conn->prepare($sql);
        // Para MySQLi, se usa bind_param
        $stmt->bind_param("s", $cutoffDate);
        $stmt->execute();
        $rowCount = $stmt->affected_rows; // En MySQLi se usa affected_rows

        $results[$table] = "{$rowCount} registros eliminados.";
        $totalDeleted += $rowCount;
    }

    $summary = [
        'success' => true,
        'message' => 'Limpieza de reportes antiguos completada.',
        'cutoff_date' => $cutoffDate,
        'details' => $results,
        'total_records_deleted' => $totalDeleted
    ];

    if ($isCLI) {
        // Imprimir resumen en formato legible para la consola
        echo "Resumen de la limpieza:\n";
        echo "Fecha de corte: " . $summary['cutoff_date'] . "\n";
        foreach ($summary['details'] as $table => $message) {
            echo "- Tabla '{$table}': {$message}\n";
        }
        echo "Total de registros eliminados: " . $summary['total_records_deleted'] . "\n";
    } else {
        // Si se accede desde el navegador, devolver un JSON
        echo json_encode($summary, JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    $errorResponse = [
        'success' => false,
        'error' => 'Error durante el proceso de limpieza.',
        'details' => $e->getMessage() // Mostrar siempre el detalle del error para depuración
    ];
    
    if (!$isCLI) http_response_code(500);
    echo ($isCLI ? "ERROR: " : "") . json_encode($errorResponse, JSON_PRETTY_PRINT) . "\n";
}
?>