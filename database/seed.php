<?php
/**
 * ES: Script de siembra de datos. Lee database/seed_data.json (fuente única
 * de verdad) y lo inserta en MySQL. Es seguro ejecutarlo varias veces: vacía
 * las tablas antes de volver a insertar (TRUNCATE).
 *
 * Uso:
 *   c:\xampp\php\php.exe database\seed.php
 *
 * EN: Data seeding script. Reads database/seed_data.json (single source of
 * truth) and inserts it into MySQL. Safe to run multiple times: truncates
 * the tables before re-inserting.
 *
 * Usage:
 *   c:\xampp\php\php.exe database\seed.php
 */

require_once __DIR__ . '/../backend/config/database.php';

$jsonPath = __DIR__ . '/seed_data.json';
$raw = file_get_contents($jsonPath);
if ($raw === false) {
    fwrite(STDERR, "No se pudo leer $jsonPath\n");
    exit(1);
}

$data = json_decode($raw, true);
if ($data === null) {
    fwrite(STDERR, "JSON inválido en $jsonPath: " . json_last_error_msg() . "\n");
    exit(1);
}

$pdo = get_db_connection();

// ES: Desactivar temporalmente las llaves foráneas para poder truncar en
//     cualquier orden, luego reactivarlas.
// EN: Temporarily disable foreign keys so tables can be truncated in any
//     order, then re-enable them.
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('TRUNCATE TABLE time_records');
$pdo->exec('TRUNCATE TABLE users');
$pdo->exec('TRUNCATE TABLE clients');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// --- Usuarios / Users -------------------------------------------------
$insertUser = $pdo->prepare(
    'INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)'
);
$userIds = [];
foreach ($data['users'] as $u) {
    $hash = password_hash($u['password'], PASSWORD_BCRYPT);
    $insertUser->execute([$u['username'], $hash, $u['full_name'], $u['role']]);
    $userIds[$u['username']] = (int) $pdo->lastInsertId();
}
echo 'Usuarios creados: ' . count($userIds) . "\n";

// --- Clientes / Clients -------------------------------------------------
$insertClient = $pdo->prepare('INSERT INTO clients (name) VALUES (?)');
$clientIds = [];
foreach ($data['clients'] as $clientName) {
    $insertClient->execute([$clientName]);
    $clientIds[$clientName] = (int) $pdo->lastInsertId();
}
echo 'Clientes creados: ' . count($clientIds) . "\n";

// --- Registros de horas / Time records ----------------------------------
$insertRecord = $pdo->prepare(
    'INSERT INTO time_records
        (consultant_id, client_id, work_date, start_time, end_time, hours, description, billable)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$count = 0;
foreach ($data['time_records'] as $r) {
    $hours = hours_between($r['start'], $r['end']);
    $insertRecord->execute([
        $userIds[$r['username']],
        $clientIds[$r['client']],
        $r['date'],
        $r['start'],
        $r['end'],
        $hours,
        $r['description'],
        $r['billable'] ? 1 : 0,
    ]);
    $count++;
}
echo "Registros de horas creados: $count\n";
echo "Siembra completada.\n";

/**
 * ES: Calcula horas decimales entre dos horas "HH:MM".
 * EN: Computes decimal hours between two "HH:MM" times.
 */
function hours_between(string $start, string $end): float
{
    [$sh, $sm] = array_map('intval', explode(':', $start));
    [$eh, $em] = array_map('intval', explode(':', $end));
    return round((($eh * 60 + $em) - ($sh * 60 + $sm)) / 60, 2);
}
