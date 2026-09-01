<?php
/**
 * ES: /backend/api/records.php — Registros de horas (CRUD parcial: listar,
 *     buscar, crear, borrar; no se permite editar en este ejercicio).
 *
 *   GET    ?q=&client_id=&month=YYYY-MM&consultant_id=   Listar / buscar
 *   POST   { client_id, work_date, start_time, end_time, description, billable }
 *   DELETE ?id=123
 *
 * Reglas de autorización (ver backend/includes/auth.php):
 *   - Todas las operaciones requieren sesión iniciada (autenticación).
 *   - Un consultor solo ve y busca en SUS PROPIOS registros; un admin ve
 *     todos o puede filtrar por consultor (?consultant_id=).
 *     [Decisión de negocio documentada en NOTES.md: la visibilidad de
 *     datos financieros/de horas de otros consultores se restringe igual
 *     que en /api/summary.php, por consistencia.]
 *   - Al crear, el dueño del registro (consultant_id) se toma SIEMPRE de la
 *     sesión del servidor, nunca del cuerpo enviado por el cliente. Este es
 *     el bug de autorización explícito que pide corregir el ejercicio.
 *   - Al borrar, un consultor solo puede borrar sus propios registros; un
 *     admin puede borrar cualquiera (autorización por dueño/rol).
 *
 * EN: /backend/api/records.php — Time records (partial CRUD: list, search,
 *     create, delete; editing is out of scope for this exercise).
 *
 *   GET    ?q=&client_id=&month=YYYY-MM&consultant_id=   List / search
 *   POST   { client_id, work_date, start_time, end_time, description, billable }
 *   DELETE ?id=123
 *
 * Authorization rules (see backend/includes/auth.php):
 *   - Every operation requires a logged-in session (authentication).
 *   - A consultant only sees/searches THEIR OWN records; an admin sees all
 *     or can filter by consultant (?consultant_id=).
 *     [Business decision documented in NOTES.md: visibility of other
 *     consultants' financial/hours data is restricted the same way as in
 *     /api/summary.php, for consistency.]
 *   - On create, the record owner (consultant_id) is ALWAYS taken from the
 *     server session, never from the client-supplied body. This is the
 *     explicit authorization bug the exercise asks to fix.
 *   - On delete, a consultant may only delete their own records; an admin
 *     may delete any record (role/owner authorization).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_auth();
$pdo = get_db_connection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handle_list($pdo, $user);
        break;
    case 'POST':
        handle_create($pdo, $user);
        break;
    case 'DELETE':
        handle_delete($pdo, $user);
        break;
    default:
        json_error('Método no permitido.', 405);
}

// =========================================================================
// GET — listar / buscar
// =========================================================================
function handle_list(PDO $pdo, array $user): void
{
    $conditions = [];
    $params = [];

    // --- Autorización de visibilidad (no solo autenticación) ---
    // --- Visibility authorization (not just authentication) ---
    if ($user['role'] === 'admin') {
        if (!empty($_GET['consultant_id'])) {
            $conditions[] = 'tr.consultant_id = ?';
            $params[] = (int) $_GET['consultant_id'];
        }
    } else {
        // ES: Un consultor jamás puede ver registros de otro, sin importar
        //     qué envíe en la query string.
        // EN: A consultant can never see another consultant's records, no
        //     matter what the query string says.
        $conditions[] = 'tr.consultant_id = ?';
        $params[] = (int) $user['id'];
    }

    if (!empty($_GET['client_id'])) {
        $conditions[] = 'tr.client_id = ?';
        $params[] = (int) $_GET['client_id'];
    }

    if (!empty($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
        $conditions[] = "DATE_FORMAT(tr.work_date, '%Y-%m') = ?";
        $params[] = $_GET['month'];
    }

    if (!empty($_GET['q'])) {
        // ES: Búsqueda simple por texto en cliente, descripción o
        //     consultor, usando LIKE con parámetro enlazado (sin
        //     concatenar SQL) para evitar inyección SQL.
        // EN: Simple text search across client, description or
        //     consultant, using LIKE with a bound parameter (no SQL
        //     string concatenation) to avoid SQL injection.
        $conditions[] = '(c.name LIKE ? OR tr.description LIKE ? OR u.full_name LIKE ?)';
        $like = '%' . $_GET['q'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $sql = "SELECT tr.id, tr.work_date, tr.start_time, tr.end_time, tr.hours,
                   tr.description, tr.billable,
                   tr.consultant_id, u.full_name AS consultant_name,
                   tr.client_id, c.name AS client_name
            FROM time_records tr
            JOIN users u   ON u.id = tr.consultant_id
            JOIN clients c ON c.id = tr.client_id
            $where
            ORDER BY tr.work_date DESC, tr.start_time DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    mark_overlaps($records);

    json_response(['records' => $records]);
}

/**
 * ES: Decisión de negocio (ver NOTES.md): los traslapes de horario del
 *     mismo consultor en el mismo día NO se bloquean al crear el registro
 *     (un consultor puede tener razones legítimas: cambios de contexto
 *     rápidos, solapamiento real de reuniones, corrección posterior). En
 *     su lugar, se detectan y se marcan aquí para que se muestren
 *     visualmente en el frontend y el propio consultor o un admin puedan
 *     revisarlos.
 * EN: Business decision (see NOTES.md): same-consultant, same-day time
 *     overlaps are NOT blocked when creating a record (a consultant may
 *     have legitimate reasons: fast context switching, genuinely
 *     overlapping meetings, later correction). Instead they are detected
 *     and flagged here so the frontend can surface them visually for the
 *     consultant or an admin to review.
 */
function mark_overlaps(array &$records): void
{
    foreach ($records as &$r) {
        $r['overlaps'] = false;
    }
    unset($r);

    $byConsultantDate = [];
    foreach ($records as $i => $r) {
        $key = $r['consultant_id'] . '|' . $r['work_date'];
        $byConsultantDate[$key][] = $i;
    }

    foreach ($byConsultantDate as $indexes) {
        if (count($indexes) < 2) {
            continue;
        }
        for ($a = 0; $a < count($indexes); $a++) {
            for ($b = $a + 1; $b < count($indexes); $b++) {
                $r1 = $records[$indexes[$a]];
                $r2 = $records[$indexes[$b]];
                if ($r1['start_time'] < $r2['end_time'] && $r2['start_time'] < $r1['end_time']) {
                    $records[$indexes[$a]]['overlaps'] = true;
                    $records[$indexes[$b]]['overlaps'] = true;
                }
            }
        }
    }
}

// =========================================================================
// POST — crear
// =========================================================================
function handle_create(PDO $pdo, array $user): void
{
    require_same_origin_header();

    $body = read_json_body();

    $clientId = (int) ($body['client_id'] ?? 0);
    $workDate = (string) ($body['work_date'] ?? '');
    $startTime = (string) ($body['start_time'] ?? '');
    $endTime = (string) ($body['end_time'] ?? '');
    $description = trim((string) ($body['description'] ?? ''));
    $billable = !empty($body['billable']) ? 1 : 0;

    // --- Validación de entrada / input validation ---
    if ($clientId <= 0) {
        json_error('Selecciona un cliente válido.', 400);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate) || !strtotime($workDate)) {
        json_error('Fecha inválida.', 400);
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        json_error('Hora de inicio/fin inválida (formato HH:MM).', 400);
    }
    if ($startTime >= $endTime) {
        json_error('La hora de inicio debe ser anterior a la hora de fin.', 400);
    }
    if (mb_strlen($description) > 255) {
        json_error('La descripción no puede superar 255 caracteres.', 400);
    }

    $stmt = $pdo->prepare('SELECT id FROM clients WHERE id = ?');
    $stmt->execute([$clientId]);
    if ($stmt->fetch() === false) {
        json_error('El cliente indicado no existe.', 400);
    }

    $hours = round((strtotime($endTime) - strtotime($startTime)) / 3600, 2);
    if ($hours <= 0 || $hours > 24) {
        json_error('El número de horas calculado es inválido.', 400);
    }

    // --- Punto crítico de seguridad ---
    // El dueño del registro es SIEMPRE el usuario de la sesión activa.
    // Nunca se lee consultant_id / user_id / owner_id del body enviado
    // por el cliente: eso permitiría a cualquier usuario autenticado
    // registrar horas (y facturación) a nombre de otro consultor.
    //
    // --- Critical security point ---
    // The record owner is ALWAYS the currently logged-in user. We never
    // read a consultant_id / user_id / owner_id field from the
    // client-supplied body: doing so would let any authenticated user
    // log hours (and billing) under another consultant's name.
    $consultantId = (int) $user['id'];

    $insert = $pdo->prepare(
        'INSERT INTO time_records
            (consultant_id, client_id, work_date, start_time, end_time, hours, description, billable)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([$consultantId, $clientId, $workDate, $startTime, $endTime, $hours, $description, $billable]);
    $newId = (int) $pdo->lastInsertId();

    // ES: Revisamos si el nuevo registro se traslapa con otro del mismo
    //     consultor el mismo día, para devolver una advertencia (no un
    //     error) — ver mark_overlaps() y la decisión en NOTES.md.
    // EN: Check whether the new record overlaps another one from the same
    //     consultant on the same day, to return a warning (not an error)
    //     — see mark_overlaps() and the decision in NOTES.md.
    $overlapStmt = $pdo->prepare(
        'SELECT id FROM time_records
         WHERE consultant_id = ? AND work_date = ? AND id != ?
           AND start_time < ? AND ? < end_time'
    );
    $overlapStmt->execute([$consultantId, $workDate, $newId, $endTime, $startTime]);
    $hasOverlap = $overlapStmt->fetch() !== false;

    json_response([
        'id' => $newId,
        'warning' => $hasOverlap
            ? 'Este registro se traslapa en horario con otro registro tuyo del mismo día.'
            : null,
    ], 201);
}

// =========================================================================
// DELETE — borrar
// =========================================================================
function handle_delete(PDO $pdo, array $user): void
{
    require_same_origin_header();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('Falta el id del registro a borrar.', 400);
    }

    $stmt = $pdo->prepare('SELECT id, consultant_id FROM time_records WHERE id = ?');
    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if ($record === false) {
        json_error('El registro no existe.', 404);
    }

    // --- Autorización por dueño/rol ---
    // --- Owner/role authorization ---
    if (!can_manage_record($user, (int) $record['consultant_id'])) {
        json_error('No puedes borrar un registro que no te pertenece.', 403);
    }

    $del = $pdo->prepare('DELETE FROM time_records WHERE id = ?');
    $del->execute([$id]);

    json_response(['ok' => true]);
}
