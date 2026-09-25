<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);

$user = isset($json['user']) ? trim($json['user']) : '';
$pass = isset($json['pass']) ? (string)$json['pass'] : '';

if ($user === ADMIN_USER && password_verify($pass, ADMIN_PASS_HASH)) {
    // Evita fijación de sesión regenerando el id al autenticar
    session_regenerate_id(true);
    $_SESSION['is_admin'] = true;
    echo json_encode(['ok' => true]);
} else {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Usuario o contraseña incorrectos']);
}
