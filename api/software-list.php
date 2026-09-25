<?php
/**
 * Lista los archivos disponibles en la carpeta compartida de software (ruta UNC física,
 * configurada por el admin en api/config-save.php como "carpetaFisica").
 *
 * Importante: esto requiere que el proceso de PHP (el App Pool de IIS) tenga permisos de
 * LECTURA sobre esa ruta UNC. Si el App Pool corre como ApplicationPoolIdentity (el valor
 * por defecto), normalmente NO tiene acceso a recursos de red — hay que configurar el App
 * Pool para que corra con una cuenta de dominio/usuario que sí tenga permisos sobre el
 * recurso compartido (IIS Manager → Grupos de aplicaciones → Configuración avanzada →
 * Identidad → cuenta personalizada).
 */
header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/../data';
$configFile = $dataDir . '/config.json';

$config = ['carpetaFisica' => '', 'carpetaCompartida' => ''];
if (file_exists($configFile)) {
    $stored = json_decode(file_get_contents($configFile), true);
    if ($stored) $config = array_merge($config, $stored);
}

$path = trim($config['carpetaFisica'] ?? '');

if ($path === '') {
    echo json_encode([
        'ok' => false,
        'error' => 'La ruta física de la carpeta compartida aún no está configurada. Un admin debe indicarla en el panel de Admin (pestaña "Software y carpeta compartida").'
    ]);
    exit;
}

if (!is_dir($path)) {
    echo json_encode([
        'ok' => false,
        'error' => 'No se pudo acceder a la carpeta: ' . $path . '. Comprueba que la ruta es correcta y que el App Pool de IIS tiene permisos de lectura sobre ese recurso de red.'
    ]);
    exit;
}

$files = [];
foreach (scandir($path) as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    $full = $path . DIRECTORY_SEPARATOR . $entry;
    if (is_dir($full)) continue; // solo archivos en el primer nivel
    $files[] = [
        'name' => $entry,
        'size' => filesize($full),
        'modified' => date('c', filemtime($full)),
    ];
}

usort($files, function($a, $b) { return strcasecmp($a['name'], $b['name']); });

echo json_encode(['ok' => true, 'path' => $path, 'files' => $files]);
