<?php
/**
 * ES: POST /backend/api/auth/logout.php — destruye la sesión del servidor.
 * EN: POST /backend/api/auth/logout.php — destroys the server session.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método no permitido.', 405);
}

$_SESSION = [];
session_destroy();

json_response(['ok' => true]);
