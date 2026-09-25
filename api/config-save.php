<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
require_admin();

$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
}

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);

if (!$json || !isset($json['programs']) || !is_array($json['programs'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Configuración inválida']);
    exit;
}

// Normaliza y valida cada programa (id único en formato slug, nombre obligatorio)
$clean = [];
$seenIds = [];
foreach ($json['programs'] as $p) {
    $name = isset($p['name']) ? trim($p['name']) : '';
    if ($name === '') continue;

    $id = isset($p['id']) && $p['id'] !== '' ? $p['id'] : $name;
    $id = strtolower(trim($id));
    $id = preg_replace('/[^a-z0-9]+/', '-', $id);
    $id = trim($id, '-');
    if ($id === '') $id = 'prog-' . substr(md5($name), 0, 6);

    // Evita colisiones de id
    $base = $id; $i = 2;
    while (isset($seenIds[$id])) { $id = $base . '-' . $i; $i++; }
    $seenIds[$id] = true;

    $clean[] = [
        'id' => $id,
        'name' => $name,
        'desc' => isset($p['desc']) ? trim($p['desc']) : '',
    ];
}

$config = [
    'carpetaCompartida' => isset($json['carpetaCompartida']) ? trim($json['carpetaCompartida']) : '',
    'carpetaFisica' => isset($json['carpetaFisica']) ? trim($json['carpetaFisica']) : '',
    'rutaHCPToolKit' => isset($json['rutaHCPToolKit']) ? trim($json['rutaHCPToolKit']) : '',
    'programs' => $clean,
];

$file = $dataDir . '/config.json';
$fp = fopen($file, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo abrir config.json para escritura']);
    exit;
}
if (flock($fp, LOCK_EX)) {
    ftruncate($fp, 0);
    fwrite($fp, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
}
fclose($fp);

echo json_encode(['ok' => true, 'config' => $config]);
