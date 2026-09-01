<?php
/**
 * ES: Arranque común de todos los endpoints del API. Se encarga de:
 *      1) Nunca mostrar errores/stack traces de PHP al cliente (solo se
 *         registran en el log del servidor) — evita fuga de información
 *         (rutas del servidor, consultas SQL, versión de PHP, etc.).
 *      2) Cargar las dependencias compartidas (respuestas JSON, sesión,
 *         conexión a BD).
 *
 * EN: Common bootstrap for every API endpoint. Responsible for:
 *      1) Never showing PHP errors/stack traces to the client (they are
 *         only written to the server log) — prevents information
 *         disclosure (server paths, SQL queries, PHP version, etc.).
 *      2) Loading shared dependencies (JSON responses, session, DB
 *         connection).
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

set_exception_handler(function (Throwable $e): void {
    error_log('[ConsultHours] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    json_error('Ocurrió un error interno. Intenta de nuevo más tarde.', 500);
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    error_log("[ConsultHours] PHP error: $message in $file:$line");
    return true; // ES: no ejecutar el manejador nativo / EN: don't run PHP's native handler
});
