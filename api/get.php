<?php
/**
 * Devuelve un único informe por su id: api/get.php?id=INF-XXXXX
 */

header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/../data';
$id = isset($_GET['id']) ? $_GET['id'] : '';

if (!preg_match('/^[A-Za-z0-9\-]+$/', $id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

$file = $dataDir . '/' . $id . '.json';

if (!file_exists($file)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Informe no encontrado']);
    exit;
}

$content = file_get_contents($file);
$json = json_decode($content, true);

if (!$json) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'El archivo guardado está corrupto']);
    exit;
}

echo json_encode(['ok' => true, 'item' => $json]);
