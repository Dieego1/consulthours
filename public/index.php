<?php
/**
 * ES: Punto de entrada principal del programa (movido aquí, a public/, en
 * vez de vivir suelto en la raíz del proyecto, para que la raíz quede
 * organizada: público en un solo lugar, todo lo demás —backend/,
 * database/, scripts/— claramente separado). Sigue siendo un archivo PHP,
 * como pide el enunciado, y sigue abriéndose desde la misma URL de
 * siempre: la raíz del proyecto redirige aquí automáticamente (ver
 * ../.htaccess, DirectoryIndex).
 *
 * Simplemente redirige al SPA estático en frontend/index.html. Se hace
 * así —en vez de "include"— para que las rutas relativas de CSS/JS
 * dentro del frontend y las llamadas al backend
 * (frontend/assets/js/api.js) se resuelvan siempre igual, sin importar
 * si el usuario entra por la raíz del proyecto o visita esta URL
 * directamente.
 *
 * La ruta de destino se calcula a partir de SCRIPT_NAME (la ruta real de
 * este archivo en el servidor) en vez de escribirse "a mano", para que
 * siga funcionando igual si el proyecto se renombra o se mueve, y para
 * que dé el mismo resultado sin importar si Apache llegó aquí por
 * DirectoryIndex (visitando la raíz) o porque el navegador pidió
 * directamente /public/index.php.
 *
 * EN: Main entry point of the program (moved here, into public/, instead
 * of sitting loose at the project root, so the root stays organized:
 * public-facing in one place, everything else —backend/, database/,
 * scripts/— clearly separated). Still a PHP file, as requested, and still
 * opens from the same URL as before: the project root redirects here
 * automatically (see ../.htaccess, DirectoryIndex).
 *
 * It simply redirects to the static SPA at frontend/index.html. This is
 * done as a redirect —rather than an "include"— so that the frontend's
 * relative CSS/JS paths and its backend calls
 * (frontend/assets/js/api.js) always resolve the same way, whether the
 * user enters through the project root or visits this URL directly.
 *
 * The target path is computed from SCRIPT_NAME (this file's real path on
 * the server) instead of being hardcoded, so it keeps working if the
 * project is renamed or moved, and so it behaves the same whether Apache
 * got here via DirectoryIndex (visiting the root) or because the browser
 * requested /public/index.php directly.
 */

$publicDir = dirname($_SERVER['SCRIPT_NAME']); // ES: .../PRUEBA TECNICA/public  EN: .../PRUEBA TECNICA/public
$appRoot   = dirname($publicDir);              // ES: .../PRUEBA TECNICA        EN: .../PRUEBA TECNICA

header('Location: ' . $appRoot . '/frontend/index.html');
exit;
