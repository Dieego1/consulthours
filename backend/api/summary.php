<?php
/**
 * ES: GET /backend/api/summary.php?month=YYYY-MM&consultant_id=
 *     Resumen mensual de horas FACTURABLES por cliente.
 *
 *     Decisión de negocio (documentada en NOTES.md): un consultor solo ve
 *     su propio resumen financiero/facturable; solo un admin puede ver el
 *     resumen agregado de todos los consultores o filtrar por uno
 *     específico con ?consultant_id=. Esto es intencional y va más allá de
 *     "requiere sesión": es una regla de autorización, no solo de
 *     autenticación.
 *
 * EN: GET /backend/api/summary.php?month=YYYY-MM&consultant_id=
 *     Monthly summary of BILLABLE hours per client.
 *
 *     Business decision (documented in NOTES.md): a consultant only sees
 *     their own financial/billable summary; only an admin can see the
 *     aggregated summary across all consultants or filter by a specific
 *     one via ?consultant_id=. This is intentional and goes beyond
 *     "requires a session": it's an authorization rule, not just
 *     authentication.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Método no permitido.', 405);
}

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    json_error('Parámetro "month" inválido (formato esperado: YYYY-MM).', 400);
}

$pdo = get_db_connection();

$conditions = ["DATE_FORMAT(tr.work_date, '%Y-%m') = ?", 'tr.billable = 1'];
$params = [$month];

if ($user['role'] === 'admin') {
    if (!empty($_GET['consultant_id'])) {
        $conditions[] = 'tr.consultant_id = ?';
        $params[] = (int) $_GET['consultant_id'];
    }
    // ES: sin filtro -> agregado de todos los consultores.
    // EN: no filter -> aggregated across all consultants.
} else {
    // ES: Un consultor NUNCA ve el resumen de otro, sin importar qué
    //     consultant_id envíe en la query string.
    // EN: A consultant NEVER sees another one's summary, no matter what
    //     consultant_id is sent in the query string.
    $conditions[] = 'tr.consultant_id = ?';
    $params[] = (int) $user['id'];
}

$where = implode(' AND ', $conditions);

$sql = "SELECT c.id AS client_id, c.name AS client_name,
               ROUND(SUM(tr.hours), 2) AS billable_hours,
               COUNT(*) AS record_count
        FROM time_records tr
        JOIN clients c ON c.id = tr.client_id
        WHERE $where
        GROUP BY c.id, c.name
        ORDER BY c.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$total = 0.0;
foreach ($rows as $row) {
    $total += (float) $row['billable_hours'];
}

json_response([
    'month' => $month,
    'scope' => $user['role'] === 'admin' && empty($_GET['consultant_id']) ? 'all_consultants' : 'single_consultant',
    'clients' => $rows,
    'total_billable_hours' => round($total, 2),
]);
