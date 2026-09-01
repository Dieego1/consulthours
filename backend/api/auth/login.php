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

// --- Mitigación básica de fuerza bruta / basic brute-force mitigation ---
// ES: Máximo 5 intentos fallidos por sesión de navegador, con bloqueo
//     temporal de 30s. No sustituye un rate-limit real por IP a nivel de
//     infraestructura (ver NOTES.md), pero detiene ataques triviales.
// EN: Max 5 failed attempts per browser session, with a 30s temporary
//     lock. Not a substitute for real IP-based infra rate-limiting (see
//     NOTES.md), but stops trivial scripted attacks.
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
$_SESSION['login_locked_until'] = $_SESSION['login_locked_until'] ?? 0;

if (time() < $_SESSION['login_locked_until']) {
    $wait = $_SESSION['login_locked_until'] - time();
    json_error("Demasiados intentos fallidos. Intenta de nuevo en {$wait}s.", 429);
}

$body = read_json_body();
$username = trim((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($username === '' || $password === '') {
    json_error('Usuario y contraseña son obligatorios.', 400);
}

$pdo = get_db_connection();
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
    $_SESSION['login_attempts']++;
    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_locked_until'] = time() + 30;
        $_SESSION['login_attempts'] = 0;
    }
    json_error('Usuario o contraseña incorrectos.', 401);
}

// ES: Login correcto: reiniciar contador y regenerar el ID de sesión
//     (previene fijación de sesión / session fixation).
// EN: Successful login: reset the counter and regenerate the session ID
//     (prevents session fixation).
$_SESSION['login_attempts'] = 0;
session_regenerate_id(true);

$_SESSION['user'] = [
    'id'        => (int) $row['id'],
    'username'  => $row['username'],
    'full_name' => $row['full_name'],
    'role'      => $row['role'],
];

json_response(['user' => $_SESSION['user']]);
