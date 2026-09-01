<?php
/**
 * ES: Configuración de conexión a MySQL (XAMPP). Ajusta estas constantes si
 * tu instalación de XAMPP usa credenciales distintas (por defecto XAMPP trae
 * el usuario "root" sin contraseña).
 *
 * EN: MySQL (XAMPP) connection configuration. Adjust these constants if your
 * XAMPP install uses different credentials (XAMPP ships with user "root"
 * and no password by default).
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'consulthours');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * ES: Devuelve una conexión PDO reutilizable (singleton por request).
 *     Usa excepciones ante errores y placeholders con nombre desactivados
 *     por defecto (usamos "?" en todo el proyecto por simplicidad).
 *
 * EN: Returns a reusable PDO connection (singleton per request).
 *     Throws exceptions on errors.
 */
function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // ES: consultas preparadas reales / EN: real prepared statements
        ]);
    }

    return $pdo;
}
