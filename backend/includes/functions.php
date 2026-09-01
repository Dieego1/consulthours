<?php
/**
 * ES: Funciones auxiliares compartidas por todos los endpoints del API:
 *     respuestas JSON uniformes y lectura segura del cuerpo de la petición.
 * EN: Helper functions shared by every API endpoint: uniform JSON
 *     responses and safe request-body parsing.
 */

header('Content-Type: application/json; charset=utf-8');

/**
 * ES: Envía una respuesta JSON con el código de estado dado y termina
 *     la ejecución.
 * EN: Sends a JSON response with the given status code and stops
 *     execution.
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * ES: Atajo para respuestas de error uniformes: { "error": "..." }.
 *     Nunca se filtran detalles internos (mensajes de PDO, rutas, etc.)
 *     al cliente; esos detalles se registran en error_log().
 * EN: Shortcut for uniform error responses: { "error": "..." }.
 *     Internal details (PDO messages, paths, etc.) are never leaked to
 *     the client; they are written to error_log() instead.
 */
function json_error(string $message, int $status = 400): void
{
    json_response(['error' => $message], $status);
}

/**
 * ES: Lee y decodifica el cuerpo JSON de la petición. Si el JSON es
 *     inválido, responde 400 y termina.
 * EN: Reads and decodes the request's JSON body. If the JSON is
 *     invalid, responds 400 and stops.
 */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_error('Cuerpo de la petición inválido (se esperaba JSON).', 400);
    }
    return $decoded;
}

/**
 * ES: Mitigación ligera de CSRF para un API same-origin basada en cookies
 *     de sesión: exige un encabezado personalizado que un <form> HTML
 *     clásico no puede enviar y que, si se envía desde otro origen vía
 *     fetch/XHR, dispara un preflight CORS que este servidor (sin
 *     cabeceras Access-Control-Allow-Origin) rechaza. Se combina con
 *     la cookie de sesión SameSite=Lax configurada en auth.php.
 * EN: Lightweight CSRF mitigation for a same-origin, cookie-based API:
 *     requires a custom header that a classic HTML <form> cannot send,
 *     and that, if sent cross-origin via fetch/XHR, triggers a CORS
 *     preflight this server rejects (no Access-Control-Allow-Origin
 *     headers are ever sent). Combined with the SameSite=Lax session
 *     cookie configured in auth.php.
 */
function require_same_origin_header(): void
{
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'ConsultHours') {
        json_error('Petición rechazada (falta encabezado esperado).', 403);
    }
}
