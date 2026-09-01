<?php
/**
 * ES: GET /backend/api/consultants.php — lista de consultores, solo para
 *     administradores (se usa en los selectores de filtro "ver de qué
 *     consultor" en registros y resumen). Un consultor no necesita ni debe
 *     ver esta lista.
 * EN: GET /backend/api/consultants.php — list of consultants, admin only
 *     (used by the "filter by consultant" dropdowns in records and
 *     summary). A consultant does not need, and should not have, this
 *     list.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Método no permitido.', 405);
}

$pdo = get_db_connection();
$consultants = $pdo->query(
    "SELECT id, full_name FROM users WHERE role = 'consultant' ORDER BY full_name ASC"
)->fetchAll();

json_response(['consultants' => $consultants]);
