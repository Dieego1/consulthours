<?php
/**
 * ES: GET /backend/api/clients.php — lista de clientes (para el selector del
 *     formulario de registro y los filtros de búsqueda). Requiere sesión
 *     iniciada; cualquier rol puede leer el catálogo de clientes.
 * EN: GET /backend/api/clients.php — list of clients (for the record form's
 *     dropdown and search filters). Requires a logged-in session; any role
 *     may read the client catalog.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Método no permitido.', 405);
}

$pdo = get_db_connection();
$clients = $pdo->query('SELECT id, name FROM clients ORDER BY name ASC')->fetchAll();

json_response(['clients' => $clients]);
