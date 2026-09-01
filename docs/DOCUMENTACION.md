# Documentación técnica — ConsultHours

Documentación completa del sistema: qué hace, cómo está organizado, cómo
funciona cada pieza y cómo se relacionan entre sí. Para el resumen de
seguridad y las decisiones de negocio, ver [`NOTES.md`](../NOTES.md); para
instalación rápida, ver [`README.md`](../README.md).

## Índice

1. [Qué es ConsultHours](#1-qué-es-consulthours)
2. [Arquitectura general](#2-arquitectura-general)
3. [Modelo de datos](#3-modelo-de-datos)
4. [Backend: API PHP](#4-backend-api-php)
5. [Seguridad: autenticación y autorización](#5-seguridad-autenticación-y-autorización)
6. [Frontend: SPA en JavaScript vanilla](#6-frontend-spa-en-javascript-vanilla)
7. [Animaciones 3D](#7-animaciones-3d)
8. [Verificación en Python y automatización](#8-verificación-en-python-y-automatización)
9. [Flujo completo de una petición](#9-flujo-completo-de-una-petición)
10. [Cómo extender el sistema](#10-cómo-extender-el-sistema)

---

## 1. Qué es ConsultHours

Una consultora necesita saber, cada mes, cuántas horas facturables trabajó
cada consultor para cada cliente. ConsultHours resuelve tres cosas:

1. **Captura de horas**: cada consultor registra en qué cliente trabajó,
   qué día, en qué horario y si esas horas son facturables o no.
2. **Búsqueda**: cualquier registro se puede filtrar por texto, cliente,
   mes y (si eres administrador) por consultor.
3. **Resumen mensual**: cuántas horas facturables se acumularon por cliente
   en un mes dado — el número que la consultora usaría para facturar.

Todo esto con dos niveles de control de acceso: **autenticación** (¿quién
eres?) y **autorización** (¿qué puedes ver o hacer con lo que encontraste?).

## 2. Arquitectura general

```
 http://localhost/PRUEBA TECNICA/   (.htaccess: DirectoryIndex public/index.php)
              │
              ▼
     public/index.php  ──(redirect 302)──►  frontend/index.html
                                                    │
┌─────────────────────┐         ┌──────────────────────────┐        ┌───────────────┐
│  Navegador           │  fetch  │  Backend PHP (Apache)     │  PDO   │  MySQL         │
│  frontend/index.html │ ──────► │  backend/api/*.php        │ ─────► │  consulthours  │
│  (SPA, JS vanilla)    │ ◄────── │  sesión PHP + PDO         │ ◄───── │  (XAMPP)       │
└─────────────────────┘  JSON   └──────────────────────────┘  filas └───────────────┘
                                            ▲
                                            │ HTTP (login + /api/summary)
                                            │
                                 ┌──────────────────────────┐
                                 │  scripts/verify_summary.py│
                                 │  (verificación externa)   │
                                 └──────────────────────────┘
```

- **Todo se sirve desde el mismo origen** (`http://localhost/PRUEBA TECNICA/…`):
  no hay CORS que configurar ni cabeceras `Access-Control-Allow-Origin` que
  abrir, lo cual reduce la superficie de ataque (ver `NOTES.md` §1.11).
- `public/index.php` es solo un *redirect* a `frontend/index.html` (ver
  §6.1) — es el "index principal" que pide el enunciado, en PHP. Vive en
  `public/` (no en la raíz del proyecto) para que quede claro cuál es la
  única carpeta pensada para abrirse por URL; un `.htaccess` en la raíz
  (`DirectoryIndex public/index.php`) hace que la URL de siempre
  (`http://localhost/PRUEBA%20TECNICA/`) se abra exactamente igual.
- `database/`, `scripts/`, `backend/config/` y `backend/includes/` tienen
  su propio `.htaccess` con `Require all denied`: nunca deben abrirse
  directamente desde el navegador (ver `NOTES.md` §1.12).
- El backend es **sin framework**: cada endpoint es un archivo PHP plano
  bajo `backend/api/`, con lógica compartida en `backend/includes/`.
- El frontend es **JavaScript vanilla**: sin build step, sin npm, sin
  bundlers — se abre directamente en el navegador servido por Apache.

## 3. Modelo de datos

Cuatro tablas en MySQL (`database/schema.sql`):

```
users                          clients                    time_records
─────                          ───────                    ────────────
id            PK                id          PK             id                 PK
username      UNIQUE            name        UNIQUE          consultant_id      FK → users.id
password_hash                                                client_id         FK → clients.id
full_name                                                    work_date
role   ENUM('admin',                                         start_time
            'consultant')                                    end_time
created_at                                                    hours (derivado de start/end)
                                                               description
                                                               billable  (0/1)
                                                               created_at

login_attempts
──────────────
id             PK
username        (sin FK a users -- se registra igual aunque el usuario no exista)
ip_address
attempted_at
```

`login_attempts` no tiene llave foránea hacia `users` a propósito: un
intento de login con un nombre de usuario que no existe también debe
contar para el bloqueo (si no, alguien podría usarlo para "adivinar" qué
usuarios existen probando cuáles nunca se bloquean). Ver
`backend/api/auth/login.php` y `NOTES.md` §1.7.

Puntos importantes del diseño:

- **`hours` es derivado, no capturado directamente.** El consultor elige
  hora de inicio y fin; el backend calcula las horas
  (`backend/api/records.php::handle_create`). Esto evita que alguien mande
  `hours: 999` sin relación con el horario real.
- **`consultant_id` nunca viaja en el body de creación.** Se toma de la
  sesión (ver `NOTES.md` §1.1). Por eso no hay, a propósito, un campo
  "consultor" en el formulario de nuevo registro del frontend.
- El índice `idx_consultant_date (consultant_id, work_date)` existe porque
  la detección de traslapes (§4.4) agrupa exactamente por esas dos
  columnas.

`database/seed_data.json` es la **única fuente de datos de prueba**: tanto
`database/seed.php` (puebla MySQL desde la línea de comandos) como
`scripts/verify_summary.py` (calcula lo que el API *debería* responder)
leen de ese mismo archivo, para que nunca puedan desincronizarse entre sí.
`database/seed.sql` es un tercer camino —pensado para pegarse directo en
la pestaña SQL de phpMyAdmin, sin necesitar la terminal— generado a mano a
partir de esos mismos datos (mismos usuarios, mismos clientes, mismos 20
registros, mismos hashes bcrypt reales de las contraseñas); no lo lee
ningún script en tiempo real, así que si `seed_data.json` cambia,
`seed.sql` se debe regenerar/actualizar a mano para que los tres caminos
sigan de acuerdo.

`seed.sql` empieza con `SET NAMES utf8mb4;` a propósito: sin eso, un
cliente que se conecte con una codificación distinta a UTF-8 por defecto
(por ejemplo, el cliente de línea de comandos de MySQL en Windows, que
puede tomar la página de códigos de la consola) puede guardar los
acentos corruptos de forma **permanente** en la base, aunque las tablas
estén declaradas como `utf8mb4`. Pasó de verdad durante el desarrollo —
ver el caso completo, con los bytes exactos que quedaron mal guardados y
cómo se diagnosticó, en `docs/APRENDIZAJES.md` §5.4. `backend/config/database.php`
nunca tuvo este problema porque fija `charset=utf8mb4` directo en el DSN
de PDO, sin depender de ningún valor por defecto externo.

## 4. Backend: API PHP

Todos los endpoints devuelven JSON y viven bajo `backend/api/`. Todos
requieren sesión iniciada excepto `auth/login.php`.

| Método | Ruta                              | Qué hace                                                | Quién puede |
|--------|------------------------------------|----------------------------------------------------------|-------------|
| POST   | `/backend/api/auth/login.php`      | Inicia sesión                                             | Cualquiera con credenciales válidas |
| POST   | `/backend/api/auth/logout.php`     | Cierra sesión                                              | Sesión iniciada |
| GET    | `/backend/api/auth/session.php`    | Devuelve el usuario de la sesión actual                    | Sesión iniciada |
| GET    | `/backend/api/clients.php`         | Lista de clientes (para selects)                           | Sesión iniciada |
| GET    | `/backend/api/consultants.php`     | Lista de consultores (para filtros)                        | Solo admin |
| GET    | `/backend/api/records.php`         | Lista/busca registros (`q`, `client_id`, `month`, `consultant_id`) | Sesión iniciada — ve solo lo propio salvo que sea admin |
| POST   | `/backend/api/records.php`         | Crea un registro de horas                                   | Sesión iniciada — el dueño siempre es quien está logueado |
| DELETE | `/backend/api/records.php?id=`     | Borra un registro                                            | Dueño del registro o admin |
| GET    | `/backend/api/summary.php`         | Resumen mensual de horas facturables por cliente (`month`, `consultant_id`) | Sesión iniciada — ve solo lo propio salvo que sea admin |

### 4.1 `backend/includes/bootstrap.php`

Punto de entrada común de cada endpoint (`require_once __DIR__ . '/../includes/bootstrap.php'`):
apaga `display_errors`, instala manejadores de error/excepción que nunca
filtran detalles internos, y carga `functions.php`, `auth.php` y la
conexión a base de datos.

### 4.2 `backend/config/database.php`

Devuelve una única conexión PDO por request (`get_db_connection()`), con
`PDO::ATTR_EMULATE_PREPARES => false` (sentencias preparadas reales, no
emuladas por el driver) y `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`.

### 4.3 `backend/includes/functions.php`

Utilidades compartidas: `json_response()`, `json_error()`,
`read_json_body()` y `require_same_origin_header()` (mitigación CSRF, ver
`NOTES.md` §1.6).

### 4.4 Detección de traslapes (`records.php`)

`mark_overlaps()` agrupa los registros ya cargados por
`consultant_id + work_date` y compara pares de horarios con la fórmula
clásica de solapamiento de intervalos:

```php
$r1['start_time'] < $r2['end_time'] && $r2['start_time'] < $r1['end_time']
```

Si se solapan, ambos quedan marcados `overlaps: true` en la respuesta JSON,
y el frontend los pinta con una insignia "⚠ traslape". Al **crear** un
registro se hace la misma comprobación contra la base de datos
directamente (no contra la lista ya cargada en memoria) para devolver un
`warning` en la respuesta del POST. Ver la justificación de por qué esto
no bloquea la creación en `NOTES.md` §2.1.

### 4.5 `summary.php`: cómo se calcula el resumen

```sql
SELECT c.name, ROUND(SUM(tr.hours), 2) AS billable_hours, COUNT(*) AS record_count
FROM time_records tr JOIN clients c ON c.id = tr.client_id
WHERE DATE_FORMAT(tr.work_date, '%Y-%m') = :month
  AND tr.billable = 1
  AND [tr.consultant_id = :self  |  tr.consultant_id = :filtro (admin)  |  sin filtro (admin, todos)]
GROUP BY c.id, c.name
```

Solo se suman registros con `billable = 1`. Este es exactamente el número
que `scripts/verify_summary.py` recalcula por su cuenta y compara (§8).

## 5. Seguridad: autenticación y autorización

Ver el detalle completo, con justificación de cada mitigación, en
[`NOTES.md`](../NOTES.md) §1. En resumen, dos capas **independientes**:

- **Autenticación** (`require_auth()` en `backend/includes/auth.php`):
  ¿existe una sesión de PHP válida? Si no, `401`.
- **Autorización** (`require_admin()`, `can_manage_record()`, y los
  filtros por `consultant_id` dentro de cada endpoint): dado un usuario ya
  autenticado, ¿puede hacer *esta* acción sobre *este* recurso? Si no,
  `403`, o simplemente se le muestran solo sus propios datos (para lectura).

La sesión se guarda en una cookie `HttpOnly` + `SameSite=Lax`
(`start_secure_session()`), y toda petición que cambia estado (POST,
DELETE) exige el encabezado `X-Requested-With: ConsultHours`
(`require_same_origin_header()`), que el cliente (`frontend/assets/js/api.js`)
agrega automáticamente.

## 6. Frontend: SPA en JavaScript vanilla

### 6.1 Enrutamiento de archivos y por qué `public/index.php` redirige

`frontend/index.html` referencia sus propios assets con rutas relativas a
sí mismo (`assets/css/style.css`) y llama al backend con una ruta relativa
hacia arriba (`../backend/api/...`, definida en `API_BASE` dentro de
`frontend/assets/js/api.js`). Para que esas rutas relativas siempre
resuelvan igual, `public/index.php` **no incluye** el HTML del frontend
(que rompería las rutas, porque el navegador seguiría viendo la URL de
`public/`) — en vez de eso calcula la carpeta raíz del proyecto a partir
de `dirname($_SERVER['SCRIPT_NAME'])` (la ruta real de este archivo en el
servidor, no la URL que pidió el navegador) y hace un
`header('Location: ' . $appRoot . '/frontend/index.html')` con esa ruta
absoluta.

Ese detalle —calcularla a partir de `SCRIPT_NAME` en vez de escribir
`header('Location: ../frontend/index.html')` a mano— importa porque este
archivo se puede alcanzar de dos formas distintas:

1. Visitando `public/index.php` directamente.
2. Visitando la raíz del proyecto (`http://localhost/PRUEBA%20TECNICA/`),
   donde el `.htaccess` de la raíz (`DirectoryIndex public/index.php`)
   hace que Apache sirva este mismo archivo **sin cambiar la URL que ve el
   navegador**.

Con una ruta relativa clásica, el mismo `header('Location: ../frontend/index.html')`
daría un resultado distinto según por cuál de las dos vías se llegó (el
navegador resuelve una `Location` relativa contra la URL que pidió, no
contra la ruta real del archivo en disco) — con `SCRIPT_NAME` el resultado
es siempre el mismo, sin importar cómo se llegó ahí.

### 6.2 Módulos JS (se cargan en este orden en `index.html`)

| Archivo       | Responsabilidad                                                          |
|---------------|----------------------------------------------------------------------------|
| `api.js`      | Cliente `fetch` mínimo: base URL, cookies de sesión, header CSRF, errores  |
| `auth.js`     | Estado global (`state.user`), login/logout, `escapeHtml()`, `showToast()`   |
| `records.js`  | Catálogo de clientes/consultores, búsqueda, alta y borrado de registros    |
| `summary.js`  | Resumen mensual: tabla + gráfico de barras                                 |
| `main.js`     | Arranque: decide login vs. app, pestañas, efecto de inclinación 3D         |

No hay build step ni framework: cada archivo agrega funciones/constantes al
alcance global (`window`), suficiente para el tamaño de este proyecto.

**Cuándo se recarga cada panel:** `main.js::activate(tabName)` vuelve a
pedir los datos del panel que se muestra (`loadRecords()` /
`loadSummary()`) **cada vez que se cambia de pestaña**, no solo la
primera vez — y `records.js` llama a `loadSummary()` también justo
después de crear o borrar un registro, para que las tarjetas de KPI
nunca se queden con un número viejo aunque el usuario no cambie de
pestaña. Antes de esto, cambiar de pestaña o crear/borrar un registro no
disparaba ninguna petición nueva; solo se refrescaba cuando el selector
de mes o de consultor cambiaba de *valor* — ver el caso real en
`docs/APRENDIZAJES.md` §5.5.

**Por qué `frontend/.htaccess` fuerza `Cache-Control: no-cache` en
`.js`/`.css`:** sin eso, Apache sirve esos archivos solo con
`Last-Modified`/`ETag`, y un navegador puede seguir usando una copia
vieja de un archivo que ya cambió en el servidor sin volver a
preguntar. `no-cache` no desactiva el caché: el navegador sigue
guardando una copia, pero siempre la revalida contra el servidor antes
de usarla (rápido si no cambió — `304 Not Modified` — inmediato si sí
cambió). También documentado en `docs/APRENDIZAJES.md` §5.5.

### 6.3 Control de acceso reflejado en la UI

La UI oculta/ajusta elementos según el rol (columna "Consultor" en la
tabla, filtro de consultor, badge de rol) usando el atributo
`data-role="admin"` en `<body>` (`applyRoleToDom()` en `auth.js`) combinado
con la clase CSS `.admin-only`. **Esto es solo cosmético**: la autoridad
real está en el backend (§5); ocultar un botón en el navegador no reemplaza
la verificación de permisos en el servidor, que es la que de verdad se
prueba en `NOTES.md` §4.

### 6.4 Usuarios de prueba clicables en el login

`frontend/index.html` lista los 3 usuarios de prueba con "Usuario:" y
"Contraseña:" rotulados por separado (antes iban juntos como
`admin/admin123`, sin distinguir cuál era cuál). Cada fila (`.test-user`,
con `role="button"` y `tabindex="0"` para que también funcione con
teclado) tiene `data-username`/`data-password`; `initTestUserAutofill()`
en `main.js` escucha clic y `Enter`/`Espacio`, y solo llena los campos del
formulario — **no** lo envía sola, a propósito, para no ocultarle a quien
prueba el sistema el flujo normal de login.

### 6.5 Descubribilidad del scroll horizontal en la tabla

Encontrado probando el layout a 375px de ancho (un iPhone SE, vía Chrome
DevTools Protocol en modo headless): la tabla de registros sí se podía
desplazar horizontalmente (`overflow-x: auto` en `.table-scroll` ya
funcionaba), pero nada en pantalla lo indicaba — se veía simplemente
cortada a la derecha. `updateRecordsScrollFade()` en `records.js` muestra
un degradado (`.scroll-fade-right` en `style.css`) en el borde derecho de
la tabla cuando hay más columnas de las que caben, y lo oculta cuando el
scroll ya llegó al final — se actualiza en `scroll`, en `resize`, y cada
vez que se recarga la tabla.

### 6.6 Tarjetas de KPI en el resumen mensual

La pestaña de resumen mensual ganó tres tarjetas grandes ("Horas
facturables", "Clientes con actividad", "Registros facturables") sobre
la tabla existente, con el número en degradado de color (`.stat-value`
en `style.css`, `background-clip: text`) — la misma paleta que ya usa el
resto de la app. Viven en el HTML estático (no se crean por JS), así que
`initCardTilt()` ya las detecta al cargar la página y les aplica la
misma inclinación 3D al mouse que a cualquier otra `.card-3d`; solo
`updateStatCards()` en `summary.js` actualiza los tres números, con los
mismos datos que ya trae la respuesta de `/api/summary.php` (sin ninguna
llamada extra). Funcionan igual para los 3 roles porque solo reflejan lo
que el backend ya autoriza ver — un consultor ve sus propios totales, un
admin ve el agregado o el de un consultor filtrado (ver §5, seguridad).

## 7. Animaciones 3D

Todas las animaciones viven en `frontend/assets/css/animations.css` y usan
casi exclusivamente `transform` y `opacity` (propiedades que el navegador
puede animar en la GPU sin recalcular *layout*), para que la interfaz se
sienta fluida incluso con muchas filas en la tabla:

- **Fondo**: tres formas (`bg-shape`) flotan con `translate3d` + `rotate3d`
  en bucle infinito (`float-3d`).
- **Tarjeta de login**: entra con un giro 3D (`card-flip-in`,
  `perspective` + `rotateX`).
- **Tarjetas (`.card-3d`)**: se inclinan siguiendo el mouse — `main.js`
  (`initCardTilt`) calcula el ángulo según la posición del cursor dentro de
  la tarjeta y lo escribe en las variables CSS `--rx`/`--ry`, que
  `animations.css` traduce en `rotateX`/`rotateY`.
- **Cambio de pestaña**: `tab-enter` gira el panel sobre el eje Y como una
  tarjeta que se voltea.
- **Filas de tabla**: entrada escalonada (`row-in` con `animation-delay`
  creciente por fila).
- **Barras del resumen**: el ancho se anima con `transition: width` de 0%
  al valor real, disparado en un segundo `requestAnimationFrame` para que
  el navegador registre el estado inicial antes del cambio.
- **Accesibilidad**: `@media (prefers-reduced-motion: reduce)` reduce
  todas las duraciones a ~0 para quien lo tenga configurado en su sistema.

## 8. Verificación en Python y automatización

Tres scripts en `scripts/` (protegida por `.htaccess`, no accesible por
URL — ver §2), todos sin dependencias externas, solo librería estándar de
Python.

### 8.1 `scripts/verify_summary.py`

Existe porque el enunciado pide explícitamente **no confiar** en el
número que regresa `/api/summary.php` sin comprobarlo primero. Cómo
funciona:

1. Lee `database/seed_data.json` (la misma fuente que usó `seed.php` para
   poblar MySQL).
2. Calcula, sumando `start`/`end` de cada registro con `billable: true`,
   cuántas horas facturables *debería* haber por cliente, por mes, y por
   consultor (o agregado para el admin).
3. Inicia sesión de verdad contra el backend (usuario por usuario) y llama
   a `GET /backend/api/summary.php?month=...`.
4. Compara ambos números y marca `OK` / `MISMATCH` por cada
   cliente/mes/consultor.

Se ejecuta con `python scripts/verify_summary.py`. El resultado íntegro de
la última corrida está en `NOTES.md` §4.

### 8.2 `scripts/test_api.py`

Suite de pruebas automatizadas (`unittest` de la librería estándar) que
formaliza como pruebas repetibles todo lo que se verificó a mano con
`curl` durante el desarrollo. 15 pruebas en 6 grupos:

| Clase | Qué cubre |
|---|---|
| `AuthenticationTests` | Sin sesión → 401; contraseña incorrecta → 401; login correcto nunca devuelve el hash |
| `OwnershipAndAuthorizationTests` | El bug de suplantación del punto 4 del enunciado; borrar registro ajeno → 403; borrar el propio → 200; admin borra cualquiera; un consultor nunca ve registros de otro aunque lo pida por query string |
| `SummaryVisibilityTests` | La decisión de negocio de `NOTES.md` §2.2, verificada contra el API real |
| `OverlapDetectionTests` | El ejemplo real del 6 de agosto (`NOTES.md` §2.1); crear un traslape avisa pero no bloquea |
| `SqlInjectionTests` | La búsqueda de texto no se rompe ni filtra nada con metacaracteres SQL |
| `LoginRateLimitTests` | El bloqueo por intentos fallidos (§8.3 de este documento) — usa un usuario inventado para no bloquear ninguna cuenta real de la demo |

Cada prueba que crea datos de prueba los limpia en su propio `tearDown`
(vía `DELETE` como admin), así que correrla no deja basura en la base.
Se ejecuta con `python scripts/test_api.py -v`.

### 8.3 `scripts/check_all.sh` / `scripts/check_all.ps1`

Un solo comando (una versión para Bash, otra para PowerShell, mismo
comportamiento) que corre, en orden: `php -l` sobre cada archivo PHP,
`node --check` sobre cada archivo JS, `test_api.py`, y
`verify_summary.py`. Termina con código de salida `0` solo si las cuatro
cosas pasaron.

Nota de implementación en la versión PowerShell: los dos pasos de Python
no usan la redirección nativa `2>&1` de PowerShell 5.1, sino
`cmd /c "... > archivo 2>&1"`. La razón: PowerShell 5.1 envuelve cada
línea de `stderr` de un `.exe` nativo en un objeto `NativeCommandError`
al redirigirla con `2>&1`, y `unittest` (`test_api.py`) escribe su
resultado en `stderr` por diseño — sin este rodeo, el script marcaba
"falló" incluso cuando las 15 pruebas pasaban. Ver el caso completo en
`docs/APRENDIZAJES.md` §5.2 (un problema del mismo tipo: dos herramientas
que no se entendían entre sí de la forma esperada).

## 9. Flujo completo de una petición

Ejemplo: **carla crea un nuevo registro de horas.**

```
1. Navegador: frontend/assets/js/records.js arma { client_id, work_date,
   start_time, end_time, description, billable } — SIN consultant_id.
2. api.js hace fetch('../backend/api/records.php', { method: 'POST',
   credentials: 'same-origin', headers: { 'X-Requested-With': 'ConsultHours' }, ... })
3. backend/api/records.php:
     require_auth()               -> ¿sesión válida? si no, 401.
     require_same_origin_header() -> ¿header CSRF presente? si no, 403.
     valida client_id, fecha, horario, descripción.
     consultant_id = $user['id']  -> SIEMPRE de la sesión, nunca del body.
     calcula hours = end - start.
     INSERT con PDO (prepared statement).
     revisa traslapes contra la BD -> arma { id, warning }.
4. Navegador: records.js muestra un toast ("Registro guardado.") y, si
   result.warning existe, un segundo toast de advertencia; recarga la tabla.
```

## 10. Cómo extender el sistema

- **Nuevo campo en un registro** (p. ej. una etiqueta de proyecto): agregar
  la columna en `database/schema.sql`, añadirla a `seed_data.json` y a los
  `INSERT`/`SELECT` de `backend/api/records.php`, y al formulario/tabla en
  `frontend/index.html` + `records.js`.
- **Nuevo rol** (p. ej. "gerente de cuenta" que ve varios consultores pero
  no todos): el `ENUM('admin','consultant')` de `users.role` tendría que
  crecer, y las condiciones `if ($user['role'] === 'admin')` repartidas en
  `records.php`/`summary.php` son el punto exacto donde ramificar la nueva
  regla de visibilidad.
- **Bloquear ciertos traslapes en vez de solo advertir**: el `SELECT` de
  traslape en `records.php::handle_create()` ya calcula si hay conflicto;
  cambiar el `json_response(...)` de after esa comprobación por un
  `json_error(...)` (con la condición de negocio que se decida) implementa
  un bloqueo real sin tocar el resto del sistema.
