<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
require_admin();

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$id = isset($json['id']) ? $json['id'] : '';

if (!preg_match('/^[A-Za-z0-9\-]+$/', $id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

$file = __DIR__ . '/../data/' . $id . '.json';

if (!file_exists($file)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Informe no encontrado']);
    exit;
}

if (unlink($file)) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo borrar el archivo']);
}
