<?php
/**
 * Devuelve todos los informes guardados en /data como un array JSON.
 */

header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/../data';
$items = [];

if (is_dir($dataDir)) {
    foreach (glob($dataDir . '/INF-*.json') as $file) {
        $content = file_get_contents($file);
        $json = json_decode($content, true);
        if ($json && json_last_error() === JSON_ERROR_NONE) {
            $items[] = $json;
        }
    }
}

echo json_encode(['ok' => true, 'items' => $items]);
