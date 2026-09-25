<?php
/**
 * Autenticación del panel de administración.
 * Usuario: admin
 * Contraseña: Qaz123,.-   (comparada como hash bcrypt, nunca en texto plano)
 *
 * Si en algún momento quieres cambiar la contraseña, genera un nuevo hash con:
 *   php -r "echo password_hash('NuevaContraseña', PASSWORD_DEFAULT);"
 * y sustituye el valor de ADMIN_PASS_HASH.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('ADMIN_USER', 'admin');
define('ADMIN_PASS_HASH', '$2y$12$QN/BrZmPXQSDoOvqbFRuH.2QxQvrC8BOl209nERYlCHzlPWymckDq');

function is_admin_logged_in() {
    return !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Corta la ejecución con 403 si no hay sesión de administrador activa.
 * Debe llamarse al principio de cualquier endpoint que edite o borre datos.
 */
function require_admin() {
    if (!is_admin_logged_in()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'No autorizado. Inicia sesión como administrador.']);
        exit;
    }
}
