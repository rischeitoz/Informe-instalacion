<?php
/**
 * Igual que save.php pero exige sesión de administrador.
 * Se usa cuando se edita un informe YA guardado desde el panel de admin.
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
require_admin();

$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
}

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);

if (!$json || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

if (empty($json['id']) || !preg_match('/^[A-Za-z0-9\-]+$/', $json['id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID de informe ausente o con formato inválido']);
    exit;
}

$id = $json['id'];
$file = $dataDir . '/' . $id . '.json';

$fp = fopen($file, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo abrir el archivo para escritura']);
    exit;
}

if (flock($fp, LOCK_EX)) {
    ftruncate($fp, 0);
    fwrite($fp, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
} else {
    fclose($fp);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo bloquear el archivo para escritura']);
    exit;
}
fclose($fp);

echo json_encode(['ok' => true, 'id' => $id]);
