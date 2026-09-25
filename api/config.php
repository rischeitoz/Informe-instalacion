<?php
/**
 * Devuelve la configuración del paso "Instalación de programas":
 * ruta de la carpeta compartida y catálogo de programas.
 * Es de lectura pública (todos los técnicos la necesitan al rellenar el informe);
 * solo la escritura (config-save.php) está protegida para el admin.
 */
header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/../data';
$file = $dataDir . '/config.json';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
}

$defaultConfig = [
    'carpetaCompartida' => 'Y:\\03_IT\\02_SOFTWARE_BASICO',
    'carpetaFisica' => '\\\\cibeles\\00_RECURSOS BIM\\03_IT\\02_SOFTWARE_BASICO',
    'rutaHCPToolKit' => '', // ruta UNC completa al ejecutable — pendiente de configurar por el admin
    'programs' => [
        ['id' => 'monkinet',   'name' => 'Monkinet Antivirus',  'desc' => 'Antivirus corporativo'],
        ['id' => 'mesh',       'name' => 'Mesh Agent',          'desc' => 'Gestión remota'],
        ['id' => 'zip',        'name' => '7-Zip',               'desc' => 'Compresor de archivos'],
        ['id' => 'chrome',     'name' => 'Google Chrome',       'desc' => 'Navegador web'],
        ['id' => 'office',     'name' => 'Microsoft Office',    'desc' => 'Suite ofimática'],
        ['id' => 'lightshot',  'name' => 'Lightshot',           'desc' => 'Capturas de pantalla'],
        ['id' => 'workmeter',  'name' => 'WorkMeter',           'desc' => 'Control de actividad'],
        ['id' => 'kofax',      'name' => 'KOFAX PDF',           'desc' => 'Gestión de documentos PDF'],
        ['id' => 'dna',        'name' => 'Client Setup DNA',    'desc' => 'Cliente corporativo'],
    ],
];

if (!file_exists($file)) {
    file_put_contents($file, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

$content = file_get_contents($file);
$json = json_decode($content, true);

// Auto-migración: si el config.json ya existía de una versión anterior y le faltan
// campos nuevos (como rutaHCPToolKit), se completan con el valor por defecto sin
// tocar lo que el admin ya haya configurado.
$needsMigration = false;
foreach ($defaultConfig as $key => $value) {
    if (!array_key_exists($key, $json)) {
        $json[$key] = $value;
        $needsMigration = true;
    }
}
if ($needsMigration) {
    file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

echo json_encode(['ok' => true, 'config' => $json]);
