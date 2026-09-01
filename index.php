<?php
/**
 * ES: Punto de entrada principal del programa. Es un archivo PHP (según lo
 * pedido) que simplemente redirige al SPA estático en frontend/index.html.
 * Se hace así — en vez de "include" — para que las rutas relativas de CSS/JS
 * dentro del frontend y las llamadas al backend (frontend/assets/js/api.js)
 * se resuelvan siempre igual, sin importar si el usuario abre esta URL o
 * navega directo a frontend/index.html.
 *
 * EN: Main entry point of the program. It's a PHP file (as requested) that
 * simply redirects to the static SPA at frontend/index.html. This is done
 * as a redirect — rather than an "include" — so that the frontend's
 * relative CSS/JS paths and its backend calls (frontend/assets/js/api.js)
 * always resolve the same way, whether the user opens this URL or
 * navigates straight to frontend/index.html.
 */

header('Location: frontend/index.html');
exit;
