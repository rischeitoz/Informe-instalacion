<?php
/**
 * Descarga el ejecutable HCPToolKit desde la ruta configurada por el admin
 * (data/config.json → rutaHCPToolKit). A diferencia de software-download.php,
 * aquí no se recibe ningún nombre de archivo por parámetro — la ruta completa
 * ya viene fijada desde el panel de Admin, así que no hay superficie para
 * ataques de path traversal.
 */

$dataDir = __DIR__ . '/../data';
$configFile = $dataDir . '/config.json';

$config = ['rutaHCPToolKit' => ''];
if (file_exists($configFile)) {
    $stored = json_decode(file_get_contents($configFile), true);
    if ($stored) $config = array_merge($config, $stored);
}

$path = trim($config['rutaHCPToolKit'] ?? '');

header('Content-Type: application/json; charset=utf-8');

if ($path === '') {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'error' => 'Un admin todavía no ha configurado la ubicación de HCPToolKit. Pídele que la indique en el panel de Admin → "Software y carpeta compartida".'
    ]);
    exit;
}

if (!is_file($path) || !is_readable($path)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'No se pudo acceder al ejecutable en: ' . $path . '. Comprueba que la ruta es correcta y que el App Pool de IIS tiene permisos de lectura sobre ese recurso de red.'
    ]);
    exit;
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
