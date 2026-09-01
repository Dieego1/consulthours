<?php
/**
 * ES: GET /backend/api/auth/session.php — devuelve el usuario de la sesión
 *     actual (o 401 si no hay ninguna). El frontend lo usa al cargar la
 *     página para saber si debe mostrar el login o la aplicación.
 * EN: GET /backend/api/auth/session.php — returns the current session's
 *     user (or 401 if none). Used by the frontend on page load to decide
 *     whether to show the login screen or the app.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

$user = require_auth();
json_response(['user' => $user]);
