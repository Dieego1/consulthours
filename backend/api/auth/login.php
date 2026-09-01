<?php
/**
 * ES: POST /backend/api/auth/login.php
 *     Body: { "username": "...", "password": "..." }
 *     Verifica credenciales contra el hash bcrypt guardado en MySQL y, si
 *     son válidas, crea la sesión del servidor. La contraseña jamás se
 *     compara en texto plano ni se registra en logs.
 *
 * EN: POST /backend/api/auth/login.php
 *     Body: { "username": "...", "password": "..." }
 *     Verifies credentials against the bcrypt hash stored in MySQL and,
 *     if valid, creates the server session. The password is never
 *     compared as plain text nor written to logs.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método no permitido.', 405);
}

$body = read_json_body();
$username = trim((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($username === '' || $password === '') {
    json_error('Usuario y contraseña son obligatorios.', 400);
}

$pdo = get_db_connection();

// --- Mitigación de fuerza bruta, persistida en la base de datos ---
// ES: A diferencia de una versión anterior (contador en $_SESSION), esto
//     ya no se reinicia si el atacante borra sus cookies o usa una
//     pestaña nueva: se cuenta por USUARIO, en la tabla login_attempts,
//     así que el bloqueo protege la cuenta sin importar desde qué
//     navegador/sesión llegue el siguiente intento. Máximo 5 intentos
//     fallidos en los últimos 5 minutos; las filas de más de 1 hora se
//     limpian solas para que la tabla no crezca indefinidamente.
//
//     Todo el cálculo de tiempo se hace con el reloj de MySQL (NOW() /
//     INTERVAL), nunca con time()/date() de PHP: en esta misma máquina
//     XAMPP, PHP y MySQL resultaron tener relojes con ~8 horas de
//     diferencia (zonas horarias configuradas por separado). Mezclar
//     "ahora" de PHP con timestamps guardados por MySQL hacía que el
//     bloqueo nunca se activara -- un bug real, encontrado probándolo
//     con intentos fallidos de verdad, no solo leyendo el código. La
//     corrección es usar una sola fuente de verdad para "qué hora es".
// EN: Unlike an earlier version (a counter in $_SESSION), this no longer
//     resets if the attacker clears cookies or opens a new tab: it's
//     counted per USERNAME, in the login_attempts table, so the lock
//     protects the account no matter which browser/session the next
//     attempt comes from. Max 5 failed attempts in the last 5 minutes;
//     rows older than 1 hour are cleaned up so the table never grows
//     without bound.
//
//     All the time math is done with MySQL's own clock (NOW() /
//     INTERVAL), never with PHP's time()/date(): on this very XAMPP
//     machine, PHP and MySQL turned out to have clocks about 8 hours
//     apart (separately configured timezones). Mixing PHP's "now" with
//     timestamps MySQL had stored meant the lockout never actually
//     triggered -- a real bug, found by testing it with real failed
//     attempts, not just by reading the code. The fix is to use a
//     single source of truth for "what time is it".
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_SECONDS = 300;

$pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 HOUR)');

// ES: LOGIN_WINDOW_SECONDS se interpola directo en el SQL (no como
//     parámetro enlazado) porque MySQL no acepta un placeholder "?"
//     dentro de "INTERVAL ? SECOND"; es seguro porque es una constante
//     fija de PHP (300), nunca un valor que venga del usuario.
// EN: LOGIN_WINDOW_SECONDS is interpolated directly into the SQL (not as
//     a bound parameter) because MySQL doesn't accept a "?" placeholder
//     inside "INTERVAL ? SECOND"; it's safe because it's a fixed PHP
//     constant (300), never a value coming from the user.
$attemptsStmt = $pdo->prepare(
    'SELECT COUNT(*) AS n,
            GREATEST(1, TIMESTAMPDIFF(SECOND, NOW(), MIN(attempted_at) + INTERVAL ' . LOGIN_WINDOW_SECONDS . ' SECOND)) AS retry_after
     FROM login_attempts
     WHERE username = ? AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_SECONDS . ' SECOND)'
);
$attemptsStmt->execute([$username]);
$attempts = $attemptsStmt->fetch();

if ((int) $attempts['n'] >= LOGIN_MAX_ATTEMPTS) {
    json_error("Demasiados intentos fallidos para este usuario. Intenta de nuevo en {$attempts['retry_after']}s.", 429);
}

$stmt = $pdo->prepare('SELECT id, username, password_hash, full_name, role FROM users WHERE username = ?');
$stmt->execute([$username]);
$row = $stmt->fetch();

// ES: password_verify() es seguro contra timing attacks incluso si el
//     usuario no existe (comparamos contra un hash "dummy").
// EN: password_verify() is timing-attack safe even when the user does
//     not exist (we compare against a dummy hash).
$dummyHash = '$2y$10$abcdefghijklmnopqrstuuC7q0eYQKQpQpQpQpQpQpQpQpQpQpQe';
$hashToCheck = $row['password_hash'] ?? $dummyHash;
$valid = password_verify($password, $hashToCheck) && $row !== false;

if (!$valid) {
    $insertAttempt = $pdo->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)');
    $insertAttempt->execute([$username, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    json_error('Usuario o contraseña incorrectos.', 401);
}

// ES: Login correcto: limpiar los intentos fallidos de este usuario y
//     regenerar el ID de sesión (previene fijación de sesión / session
//     fixation).
// EN: Successful login: clear this user's failed attempts and regenerate
//     the session ID (prevents session fixation).
$pdo->prepare('DELETE FROM login_attempts WHERE username = ?')->execute([$username]);
session_regenerate_id(true);

$_SESSION['user'] = [
    'id'        => (int) $row['id'],
    'username'  => $row['username'],
    'full_name' => $row['full_name'],
    'role'      => $row['role'],
];

json_response(['user' => $_SESSION['user']]);
