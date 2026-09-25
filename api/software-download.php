<?php
/**
 * Descarga un archivo de la carpeta compartida de software: api/software-download.php?file=NOMBRE
 * Solo permite servir archivos que estén DIRECTAMENTE dentro de la carpeta configurada
 * (sin subcarpetas, sin "..", sin rutas absolutas) para evitar acceso fuera de esa carpeta.
 */

$dataDir = __DIR__ . '/../data';
$configFile = $dataDir . '/config.json';

$config = ['carpetaFisica' => ''];
if (file_exists($configFile)) {
    $stored = json_decode(file_get_contents($configFile), true);
    if ($stored) $config = array_merge($config, $stored);
}

$path = trim($config['carpetaFisica'] ?? '');
$name = isset($_GET['file']) ? $_GET['file'] : '';

header('Content-Type: application/json; charset=utf-8');

// El nombre de archivo no puede contener separadores de ruta ni ".."
if ($name === '' || strpos($name, '/') !== false || strpos($name, '\\') !== false || strpos($name, '..') !== false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nombre de archivo inválido']);
    exit;
}

if ($path === '' || !is_dir($path)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'La carpeta compartida no está disponible']);
    exit;
}

$full = $path . DIRECTORY_SEPARATOR . $name;

// Verificación extra: el archivo resuelto debe seguir estando dentro de $path
$realBase = realpath($path);
$realFile = realpath($full);
if ($realBase === false || $realFile === false || strpos($realFile, $realBase) !== 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso no permitido']);
    exit;
}

if (!is_file($realFile)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Archivo no encontrado']);
    exit;
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($realFile) . '"');
header('Content-Length: ' . filesize($realFile));
header('X-Content-Type-Options: nosniff');
readfile($realFile);
exit;
