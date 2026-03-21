<?php
declare(strict_types=1);
/**
 * Lista ramas OTA permitidas (desde manifiesto + OTA_ALLOWED_BRANCHES).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/OtaManifestHelper.php';

try {
    $manifest = OtaManifestHelper::loadManifest();
    $ids = OtaManifestHelper::allowedBranchIds($manifest);
    $branches = [];
    foreach ($ids as $id) {
        $e = $manifest['branches'][$id];
        $branches[] = [
            'id' => $id,
            'version' => $e['version'],
        ];
    }
    echo json_encode([
        'success' => true,
        'branches' => $branches,
        'manifest_url' => OtaManifestHelper::manifestUrl(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
