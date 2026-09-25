<?php
/**
 * Guarda un informe de instalación como archivo JSON en /data.
 * Recibe el objeto "data" completo (tal cual lo genera el front-end) por POST en formato JSON.
 */

header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/../data';

if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo crear la carpeta de datos']);
        exit;
    }
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

if (empty($json['createdAt'])) {
    $json['createdAt'] = date('c');
}

$id = $json['id'];
$file = $dataDir . '/' . $id . '.json';

$fp = fopen($file, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo abrir el archivo para escritura (revisa permisos de la carpeta data)']);
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
