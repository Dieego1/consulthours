<?php
/**
 * ES: Autenticación y autorización basadas en sesión de PHP.
 *
 *  - Autenticación: ¿hay una sesión iniciada? (require_auth)
 *  - Autorización:  ¿el usuario de esa sesión puede hacer ESTA acción sobre
 *                    ESTE recurso? (require_admin, can_manage_record, etc.)
 *
 * Son dos capas independientes a propósito: un endpoint puede exigir
 * autenticación sin más (ver registros propios) o autenticación +
 * autorización de rol/dueño (borrar un registro ajeno).
 *
 * EN: PHP-session-based authentication and authorization.
 *
 *  - Authentication: is there a logged-in session? (require_auth)
 *  - Authorization:  can THIS session's user perform THIS action on THIS
 *                    resource? (require_admin, can_manage_record, etc.)
 *
 * These are deliberately two independent layers: an endpoint may require
 * plain authentication (view own records) or authentication + role/owner
 * authorization (delete someone else's record).
 */

require_once __DIR__ . '/functions.php';

/**
 * ES: Arranca la sesión con cookies endurecidas (HttpOnly evita que JS
 *     lea la cookie, SameSite=Lax mitiga CSRF en navegación cruzada).
 * EN: Starts the session with hardened cookie flags (HttpOnly stops JS
 *     from reading the cookie, SameSite=Lax mitigates cross-site CSRF).
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' se deja en false porque XAMPP en local corre sobre HTTP.
        // 'secure' is left false because XAMPP locally runs over plain HTTP.
        'secure'   => false,
    ]);
    session_name('consulthours_sid');
    session_start();
}

/**
 * ES: Devuelve el usuario de la sesión actual, o null si no hay sesión.
 * EN: Returns the current session's user, or null if not logged in.
 */
function current_user(): ?array
{
    start_secure_session();
    return $_SESSION['user'] ?? null;
}

/**
 * ES: Exige una sesión iniciada. Si no la hay, responde 401 y termina.
 * EN: Requires a logged-in session. If none, responds 401 and stops.
 */
function require_auth(): array
{
    $user = current_user();
    if ($user === null) {
        json_error('Debes iniciar sesión para realizar esta acción.', 401);
    }
    return $user;
}

/**
 * ES: Exige que el usuario autenticado tenga rol de administrador.
 * EN: Requires the authenticated user to have the admin role.
 */
function require_admin(): array
{
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        json_error('No tienes permisos de administrador para esta acción.', 403);
    }
    return $user;
}

/**
 * ES: Regla de autorización de dueño: un consultor solo puede administrar
 *     (borrar) sus propios registros; un admin puede administrar cualquiera.
 * EN: Owner authorization rule: a consultant may only manage (delete)
 *     their own records; an admin may manage any record.
 */
function can_manage_record(array $user, int $recordConsultantId): bool
{
    return $user['role'] === 'admin' || (int) $user['id'] === $recordConsultantId;
}
