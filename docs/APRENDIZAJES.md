# Aprendizajes del proyecto ConsultHours / Project Learnings

## ES: Cómo usar este documento

Este documento junta, en un solo lugar, los conceptos que este proyecto
pone en práctica — no es un resumen de lo que hace el código (eso ya está
en [`docs/DOCUMENTACION.md`](DOCUMENTACION.md)), sino **por qué cada
decisión es la decisión correcta**, con la referencia exacta de dónde
verlo en el código. La idea es que sirva para defender el proyecto en una
entrevista técnica: cada sección responde a "¿por qué lo hiciste así y no
de otra forma?".

## EN: How to use this document

This document brings together, in one place, the concepts this project
puts into practice — it is not a summary of what the code does (that's
already in [`docs/DOCUMENTACION.md`](DOCUMENTACION.md)), but **why each
decision is the right one**, with the exact reference of where to see it
in the code. It's meant to help defend the project in a technical
interview: every section answers "why did you do it this way and not
another?".

---

## 1. ES: Lenguajes y herramientas — qué se usó y para qué / EN: Languages & tools — what was used and why

| Lenguaje/herramienta | Rol en este proyecto | Por qué este y no otro |
|---|---|---|
| **PHP 8** | Todo el backend: API REST-ish (`backend/api/*.php`), sesiones, conexión a MySQL. | Corre nativo en Apache de XAMPP sin build step ni proceso aparte (a diferencia de Node.js) — el requisito era "córrelo desde XAMPP". |
| **MySQL (SQL)** | Almacenamiento persistente: `users`, `clients`, `time_records` (`database/schema.sql`). | Integridad referencial real (llaves foráneas) entre consultor↔registro y cliente↔registro — le importa a este dominio que un registro huérfano sea literalmente imposible, no solo "raro". |
| **JavaScript (vanilla, sin framework)** | Toda la lógica del frontend: `fetch`, manejo de sesión, render de tablas, formularios (`frontend/assets/js/*.js`). | El tamaño del proyecto (una SPA de 2 pantallas) no justifica la complejidad de un framework + bundler; vanilla JS se abre directo en el navegador servido por Apache, sin `npm install`. |
| **HTML5** | Estructura semántica de la única página (`frontend/index.html`). | — |
| **CSS3** | Layout, componentes visuales, y **todas** las animaciones 3D (`style.css`, `animations.css`). | `transform`/`perspective`/`@keyframes` nativos alcanzan para animaciones 3D fluidas sin ninguna librería — una dependencia externa aquí sería puro riesgo sin beneficio. |
| **Python 3** | Verificación independiente del resumen (`scripts/verify_summary.py`) y suite de pruebas automatizadas de todo el API (`scripts/test_api.py`, 15 pruebas con `unittest` de la librería estándar). | **A propósito un lenguaje distinto al del backend.** Si el script de verificación estuviera en PHP y compartiera, aunque fuera sin querer, una función con `summary.php`, un bug en esa función podría "verse bien" en ambos lados. Usar Python obliga a que el cálculo se escriba dos veces, de forma independiente, en dos lenguajes distintos — así una coincidencia entre ambos sí es evidencia real de que el número es correcto. |
| **Bash / PowerShell** | Durante el desarrollo, probar el sistema real (`curl` contra los endpoints, `mysql` por consola). Como entregable: `scripts/check_all.sh`/`.ps1`, que corren linting + las dos suites de Python en un solo comando. | Verificar con peticiones HTTP reales, no solo leyendo el código, es la única forma de confirmar que la autorización realmente bloquea lo que dice bloquear (ver §8 más abajo). El script de un solo comando es la automatización que pide el enunciado de fondo: reunir PHP + JS + Python en un solo lugar verificable. |
| **Apache (`.htaccess`)** | Control de acceso a nivel de servidor: `DirectoryIndex` en la raíz, `Require all denied` en `database/`, `scripts/`, `backend/config/`, `backend/includes/`. | Seguridad en capas: aunque la aplicación esté bien escrita, si el *servidor* sirve cualquier archivo bajo `htdocs` sin distinción, un archivo como `seed_data.json` (con contraseñas de prueba) queda expuesto igual. Esto se corrigió a nivel de servidor, no de aplicación, porque es donde vive el problema. |
| **Git** | Control de versiones de todo el código. | Ver [`docs/GIT.md`](GIT.md) — sección dedicada completa, incluida la lista de cada commit. |

---

## 2. ES: Seguridad web / EN: Web security

| Concepto | Dónde se aplica | Por qué importa |
|---|---|---|
| **Autenticación ≠ Autorización** | `backend/includes/auth.php`: `require_auth()` (¿hay sesión?) vs. `require_admin()`/`can_manage_record()` (¿puede *esto*, sobre *este* recurso?) | Es el error de seguridad más común en ejercicios como este: proteger un endpoint solo con "¿hay sesión?" dejaría que cualquier consultor autenticado borre el registro de otro. Son dos preguntas distintas y el código las separa como dos funciones distintas. |
| **Nunca confiar en el dueño que manda el cliente** | `backend/api/records.php::handle_create()` — `consultant_id` sale siempre de `$user['id']` (la sesión), nunca del body del POST | Es la vulnerabilidad explícita del ejercicio original: si el servidor confía en un campo que el navegador puede editar libremente, la autorización completa se vuelve decorativa. |
| **Inyección SQL** | Toda consulta en `backend/api/*.php` usa PDO con marcadores `?` y `execute([...])`, nunca concatenación de strings | Con parámetros enlazados, el motor de base de datos nunca interpreta datos del usuario como código SQL, sin importar qué caracteres especiales incluyan. |
| **Contraseñas** | `password_hash()`/`password_verify()` (bcrypt) en `database/seed.php` y `backend/api/auth/login.php` — nunca texto plano, nunca `===` | Un hash bcrypt no se puede revertir a la contraseña original ni comparar por igualdad directa (dos hashes del mismo texto son distintos por el *salt* aleatorio); por eso existe `password_verify()`. |
| **Fijación de sesión** | `session_regenerate_id(true)` justo después de un login exitoso (`login.php`) | Sin esto, un atacante que consiga que la víctima use un ID de sesión conocido *antes* de loguearse podría reusar ese mismo ID *después* del login para suplantarla. |
| **CSRF en una API basada en cookies** | Header `X-Requested-With: ConsultHours` obligatorio en POST/DELETE (`require_same_origin_header()`) + cookie `SameSite=Lax` | Las cookies de sesión se mandan automáticamente en cualquier petición al dominio, incluso si la inició un sitio malicioso; exigir un header que un `<form>` normal no puede fijar corta ese vector. |
| **XSS** | `escapeHtml()` en `frontend/assets/js/auth.js`, usado por `records.js`/`summary.js` antes de insertar cualquier texto en el DOM | Defensa en profundidad: aunque el backend ya valide, el frontend nunca debe insertar texto ajeno como HTML crudo — si algún día un dato llega sin validar, esta es la última barrera. |
| **No filtrar detalles internos en errores** | `backend/includes/bootstrap.php`: `display_errors=0` + `set_exception_handler` que siempre responde un mensaje genérico | Un mensaje de error de PDO puede revelar la estructura exacta de una tabla o la ruta del servidor — información que un atacante usaría para el siguiente paso. |
| **Exposición de archivos por el servidor, no por la app** | `.htaccess` con `Require all denied` en `database/`, `scripts/`, `backend/config/`, `backend/includes/` | Encontrado *durante este mismo proyecto*: `http://.../database/seed_data.json` respondía `200` y exponía contraseñas de prueba en texto plano — un recordatorio de que "la aplicación es segura" y "el servidor solo sirve lo que debería" son dos afirmaciones distintas. |

---

## 3. ES: Arquitectura backend / EN: Backend architecture

- **Separación de responsabilidades sin necesitar un framework:** `config/` (conexión), `includes/` (lógica compartida: auth, respuestas JSON, bootstrap de errores), `api/` (un archivo por endpoint). Cada capa tiene un trabajo — un endpoint nunca abre su propia conexión PDO, siempre pide la de `config/database.php`.
- **Un archivo, un recurso:** `records.php` maneja `GET`/`POST`/`DELETE` sobre el mismo recurso (registros de horas) en vez de spargirlo en tres archivos — el `switch ($_SERVER['REQUEST_METHOD'])` en `backend/api/records.php` es el patrón REST más simple posible sin enrutador.
- **Same-origin en vez de CORS abierto:** frontend y backend se sirven desde el mismo dominio (`public/index.php` → `frontend/index.html`, que llama a `../backend/api/...`), así que no hace falta configurar `Access-Control-Allow-Origin` en absoluto — la opción más segura fue también la más simple.
- **Bootstrap centralizado:** cada endpoint hace un solo `require_once .../bootstrap.php` en vez de repetir configuración de errores en cada archivo — un cambio en cómo se manejan los errores se hace en un solo lugar y aplica a los diez endpoints por igual.

## 4. ES: Base de datos / EN: Database

- **Datos derivados vs. datos capturados:** `hours` en `time_records` nunca se recibe del cliente — se calcula en el servidor a partir de `start_time`/`end_time` (`backend/api/records.php`). Evita que alguien mande horas que no corresponden al horario real.
- **Llaves foráneas como contrato, no como sugerencia:** `fk_tr_consultant`/`fk_tr_client` en `database/schema.sql` hacen literalmente imposible que exista un registro de horas apuntando a un consultor o cliente que no existe — la integridad se garantiza en la base, no solo "se espera" del código de la aplicación.
- **Índices pensados para las consultas reales:** `idx_consultant_date (consultant_id, work_date)` existe porque tanto la detección de traslapes (`mark_overlaps()`) como el resumen mensual filtran exactamente por esas dos columnas — un índice que nadie usa es solo peso muerto.
- **Una sola fuente de verdad para los datos de prueba:** `database/seed_data.json` alimenta tanto `seed.php` (MySQL por consola) como `scripts/verify_summary.py` (el cálculo esperado) — si vivieran por separado, podrían desincronizarse sin que nadie lo note. `database/seed.sql` (para phpMyAdmin) es la excepción deliberada: no lo lee ningún script, así que si `seed_data.json` cambia, hay que actualizarlo a mano (ver `docs/DOCUMENTACION.md` §3).

## 5. ES: Casos reales — errores que tuve y cómo se corrigieron

Estos son cuatro casos concretos que pasaron en la práctica, no riesgos
teóricos — vale la pena poder contarlos en una entrevista tal cual, con
los mensajes de error reales incluidos.

### 5.1 phpMyAdmin y el `TRUNCATE` que fallaba

**Qué pasó:** siguiendo las instrucciones de `README.md`, pegué el
contenido de `database/seed.sql` en la pestaña **SQL** de phpMyAdmin y le
di a "Continuar". phpMyAdmin regresó este error:

```
#1701 - Cannot truncate a table referenced in a foreign key constraint
(`consulthours`.`time_records`, CONSTRAINT `fk_tr_consultant`
FOREIGN KEY (`consultant_id`) REFERENCES `consulthours`.`users` (`id`))
```

La base de datos se quedó a medias: las tablas se crearon, pero
`users` terminó vacía — y por eso el login fallaba después con
"Usuario o contraseña incorrectos" aunque las credenciales fueran
correctas.

**Por qué pasó (la causa real, no el síntoma):** el script traía
`SET FOREIGN_KEY_CHECKS = 0;` justo antes de los `TRUNCATE TABLE`, para
poder vaciar `time_records`, `users` y `clients` sin que las llaves
foráneas entre ellas lo bloquearan (esto es exactamente lo que hace
`database/seed.php` cuando se corre por línea de comandos, y ahí sí
funciona). El problema es que la interfaz de phpMyAdmin tiene su propia
casilla, **"Habilitar la revisión de las claves foráneas"**, marcada por
defecto — y esa casilla vuelve a forzar `FOREIGN_KEY_CHECKS = 1` sin
importar lo que diga el script que se está ejecutando. En MySQL/InnoDB,
`TRUNCATE` sobre una tabla referenciada por una llave foránea falla con
las llaves activas **aunque la tabla que la referencia esté vacía** — no
es un chequeo de datos, es un chequeo de que existe la relación en el
esquema.

**Cómo se diagnosticó:** en vez de adivinar, se reprodujo el error
exacto de dos formas — probando el mismo SQL por línea de comandos
(`mysql -u root < seed.sql`, donde sí funcionaba) y forzando
`SET FOREIGN_KEY_CHECKS=1` manualmente antes de correrlo por consola
(ahí sí volvió a fallar igual que en phpMyAdmin). Eso confirmó que el
problema no era el contenido del SQL en sí, sino que algo fuera del
script estaba re-activando las llaves foráneas.

**Cómo se corrigió:** en vez de intentar "convencer" a phpMyAdmin de
respetar el `SET` (frágil — dependería de que cada quien recuerde
desmarcar una casilla), se quitó la necesidad de `TRUNCATE` por
completo. El script ya empezaba con `DROP DATABASE IF EXISTS consulthours;`
seguido de `CREATE DATABASE` + `CREATE TABLE`, así que las tablas
**siempre nacen vacías** — no hacía falta vaciarlas de nuevo. Se
verificó la corrección de la forma más estricta posible: forzando
`SET FOREIGN_KEY_CHECKS=1` durante todo el script (el peor caso posible)
y también reimportándolo encima de una base ya poblada — ambas veces
terminó con los 3 usuarios, 4 clientes y 20 registros esperados, y
`scripts/verify_summary.py` siguió confirmando que los números cuadraban.

**El aprendizaje que vale la pena quedarse:** un script de base de datos
no está "probado" solo porque corrió una vez en una terminal. La
herramienta real que va a usar la otra persona (en este caso, la interfaz
de phpMyAdmin con sus propias casillas y comportamientos) puede cambiar
el resultado — y la solución más robusta casi siempre es **quitarle a la
herramienta la oportunidad de interferir**, no pelear contra su
configuración por defecto.

### 5.2 PHP y MySQL no tenían el mismo reloj

**Qué pasó:** al mejorar el bloqueo por intentos fallidos de login (antes
contaba por sesión de navegador; ahora se guarda en una tabla
`login_attempts` en MySQL, para que no se reinicie solo con borrar
cookies — ver `NOTES.md` §1.7), la primera versión simplemente no
bloqueaba nunca. Se probó lanzando 6 intentos fallidos seguidos contra la
misma cuenta y el sexto seguía respondiendo `401` normal en vez de `429`.

**Por qué pasó:** la consulta que cuenta los intentos recientes comparaba
`attempted_at` (guardado por MySQL con su propio `NOW()`) contra una
fecha límite calculada en PHP con `date('Y-m-d H:i:s', time() - 300)`.
Al comparar el reloj de cada uno a mano:

```
Hora según PHP:   2026-09-01 23:00:42
Hora según MySQL: 2026-09-01 15:00:42
```

**8 horas de diferencia**, en la misma máquina, entre dos zonas horarias
configuradas por separado (la de PHP en `php.ini`, la de MySQL en su
propia configuración). La fecha límite que calculaba PHP ("hace 5
minutos") terminaba siendo *más tarde* que cualquier `attempted_at` real
guardado por MySQL, así que la condición `attempted_at > límite` daba
falso siempre, sin importar cuántos intentos hubiera.

**Cómo se diagnosticó:** en vez de asumir un error de lógica, se
comparó la hora que reporta cada motor por separado (`php -r "echo
date('Y-m-d H:i:s');"` contra `SELECT NOW();` en MySQL) — eso hizo
evidente el desfase en dos líneas, sin necesidad de revisar la consulta
SQL primero.

**Cómo se corrigió:** en vez de intentar sincronizar los dos relojes (o
convertir manualmente entre zonas horarias, frágil y fácil de romper de
nuevo), se eliminó la necesidad de que coincidan: **todo** el cálculo de
tiempo se mueve a la consulta SQL, usando `NOW()`, `INTERVAL` y
`TIMESTAMPDIFF()` de MySQL — una sola fuente de verdad para "qué hora
es", en vez de dos relojes que tienen que estar de acuerdo. Verificado de
nuevo con 6 intentos fallidos reales: el sexto ya respondió `429` con el
tiempo de espera correcto, la contraseña válida también quedó bloqueada
mientras dura el límite (protege la cuenta, no solo los intentos malos),
y otro usuario no relacionado no se vio afectado.

**El aprendizaje que vale la pena quedarse:** cuando dos sistemas
distintos (aquí, PHP y MySQL, corriendo en la misma máquina) necesitan
ponerse de acuerdo en "qué hora es", no asumas que comparten reloj —
compáralo explícitamente. Y cuando puedas elegir, deja que **un solo**
sistema sea dueño del cálculo de tiempo (aquí, la base de datos, porque
ahí es donde vive el dato) en vez de sincronizar dos.

### 5.3 El script de automatización "pasaba" aunque las pruebas fallaran, en PowerShell

**Qué pasó:** al escribir `scripts/check_all.ps1` (la versión PowerShell
de `check_all.sh`, que corre linting + `test_api.py` + `verify_summary.py`
en un solo comando), la primera versión usaba `python ... 2>&1` para
capturar la salida de cada script de Python. Con las 15 pruebas de
`test_api.py` pasando, el paso 3 igual se reportaba como fallido, con un
`NativeCommandError` en la consola en vez del resumen limpio esperado.

**Por qué pasó:** `unittest` (lo que corre `test_api.py`) escribe su
resultado en **stderr** por diseño, no en stdout — eso es normal y no es
el problema. El problema es específico de PowerShell 5.1: al redirigir el
stderr de un ejecutable *nativo* (no un cmdlet) con `2>&1`, PowerShell
envuelve **cada línea** en un objeto `ErrorRecord`, y eso hace que `$?`
quede en `$false` aunque el proceso haya terminado con código de salida
`0`. El script estaba revisando la señal equivocada para decidir si algo
había fallado.

**Cómo se corrigió:** en vez de leer `$?` (contaminado por el envoltorio
de PowerShell), el script pasó a delegar la redirección a `cmd /c "...
> archivo 2>&1"` — `cmd.exe` no tiene ese comportamiento, así que
`$LASTEXITCODE` después de la llamada refleja el código de salida real de
Python. Verificado corriendo el script completo dos veces: con las 15
pruebas pasando (reporta éxito, `EXIT CODE: 0`) y confirmando que el
mismo patrón de "OK"/salida limpia se mantiene para los otros tres pasos.

**El aprendizaje que vale la pena quedarse:** el código de salida de un
proceso y la señal que tu *shell* usa para decirte si algo salió mal no
siempre son la misma cosa — cada shell tiene sus propias reglas para
mezclar streams, y esas reglas pueden mentir. Cuando una comprobación de
éxito/fracaso es crítica (como en un script pensado para bloquear un
commit o un pipeline de CI), vale la pena verificar esa señal de forma
explícita, no asumir que "el patrón de siempre" se comporta igual en
todas las shells.

### 5.4 Los acentos se veían como "├│" en la pantalla real (este lo encontró revisando el navegador, no yo)

**Qué pasó:** al abrir la aplicación en Chrome de verdad (no en las
pruebas automatizadas) y loguearse como admin, varias descripciones se
veían así: `Correcci├│n de bugs cr├¡ticos`, `Jim├⌐nez` en vez de
`Corrección de bugs críticos`, `Jiménez`. Este bug **no lo encontré yo
primero** — lo encontró la persona probando el sistema con sus propios
ojos en el navegador, exactamente el paso que dice `README.md` que hay
que hacer antes de entregar. Es la prueba de que "lo probé por API y con
capturas automatizadas" (lo que se hizo durante toda la sesión) no
sustituye por completo abrir la aplicación real una vez.

**Por qué pasó (y por qué no era un bug de la aplicación):** se
confirmó con `HEX(description)` en MySQL que los bytes **guardados en la
base** ya estaban mal — no era un problema de cómo el navegador
interpretaba una respuesta correcta, era el dato mismo, corrompido en
el origen. El patrón exacto (`ó` → `├│`) es la huella digital de un tipo
de corrupción muy específico: los bytes UTF-8 correctos de "ó" (`C3 B3`)
se reinterpretaron carácter por carácter bajo la página de códigos
CP437 de Windows (`0xC3` → "├", `0xB3` → "│") y **esos** caracteres se
volvieron a guardar como UTF-8. Pasó porque, durante las pruebas de esta
misma sesión, `database/seed.sql` se importó varias veces por línea de
comandos (`mysql -u root < seed.sql`) sin forzar la codificación de la
conexión — el cliente de MySQL en Windows, sin ese parámetro, puede
tomar la página de códigos de la consola en vez de UTF-8. El backend en
sí (`backend/config/database.php`, con `charset=utf8mb4` fijo en el DSN
de PDO) nunca tuvo este problema; el dato ya llegaba corrompido desde
antes de que la aplicación lo leyera.

**Cómo se diagnosticó:** se comparó el hex real guardado en la base
(`SELECT description, HEX(description) FROM time_records WHERE ...`)
contra el hex correcto de la misma frase calculado aparte
(`php -r "echo bin2hex('Corrección de bugs críticos');"`) — la
diferencia entre `c3b3` (correcto) y `e2949c` (guardado) hizo evidente
que el problema estaba en los bytes almacenados, no en cómo se mostraban.

**Cómo se corrigió:** se agregó `SET NAMES utf8mb4;` como la primera
instrucción real de `database/seed.sql`, para que la conexión quede en
UTF-8 sin importar qué codificación traiga por defecto el cliente que
ejecute el archivo (línea de comandos de Windows, phpMyAdmin, o
cualquier otro). Se verificó de la forma más estricta posible:
reimportando el archivo **sin** pasar ninguna bandera de codificación en
la línea de comandos, confiando solo en el `SET NAMES` del propio
archivo — y los bytes guardados salieron correctos. Se repobló la base
y se confirmó con una captura de pantalla real (login como admin, filtro
agosto 2026) que ya no aparece ningún carácter corrupto.

**El aprendizaje que vale la pena quedarse:** un archivo SQL pensado
para compartirse (llevárselo a otra máquina, pegarlo en phpMyAdmin,
correrlo desde una terminal distinta) no debería depender de que quien
lo ejecute tenga la codificación correcta configurada por fuera del
archivo — debe declarar su propia codificación (`SET NAMES utf8mb4`) en
vez de asumirla. Y, más en general: las pruebas automatizadas (API,
`unittest`, capturas headless) verifican comportamiento, pero no
sustituyen del todo mirar la aplicación real una vez antes de
entregarla — este bug pasó *todas* las pruebas automatizadas de la
sesión, porque ninguna comparaba el texto contra el carácter exacto
esperado, solo contra la estructura de la respuesta.

## 6. ES: Frontend / EN: Frontend

- **SPA sin framework:** cambiar de pestaña (`main.js::initTabs`) es solo `classList.toggle('active', ...)` sobre los paneles que ya están en el DOM — no hace falta un router ni un framework de componentes para dos vistas.
- **`fetch` + `async/await`, no callbacks:** `frontend/assets/js/api.js` centraliza toda petición HTTP en una sola función (`apiRequest`), así que agregar el header CSRF o manejar errores de red se hace en un solo lugar para los diez endpoints que se consumen.
- **CSS 3D real, no solo "parece 3D":** `perspective`, `transform-style: preserve-3d` y `rotateX`/`rotateY`/`translateZ` en `animations.css` — el efecto de inclinación de las tarjetas (`main.js::initCardTilt`) calcula el ángulo real según la posición del mouse dentro de cada tarjeta.
- **Solo se anima `transform`/`opacity`:** son las únicas dos propiedades CSS que el navegador puede animar en la GPU sin recalcular *layout* — es la diferencia entre una interfaz fluida y una que se siente con *lag* al tener muchas filas en pantalla.
- **Accesibilidad no es opcional:** `@media (prefers-reduced-motion: reduce)` en `animations.css` apaga las animaciones para quien lo configuró así en su sistema operativo — una decisión de diseño, no un olvido.
- **Defensa en profundidad también en el cliente:** `escapeHtml()` se usa aunque el backend ya valide — el frontend nunca asume que "ya se validó en otro lado".

## 7. ES: Control de versiones / EN: Version control

Cubierto a fondo, con la lista completa de cada commit y por qué se hizo en ese orden, en **[`docs/GIT.md`](GIT.md)**. La idea central: git no es una función del programa (la app corre igual sin `.git/`), es la herramienta con la que se **entrega el proceso de construcción**, commit por commit, en vez de un solo volcado final — que es exactamente lo que pide el enunciado del ejercicio.

---

## 8. ES: Aprendizajes de trabajar con una IA (Claude Code / Claude)

Esta sección es distinta a las anteriores: no es sobre el código, sino
sobre **el proceso de construirlo con un asistente de IA** como
colaborador activo, no como "autocompletado".

### 8.1 La IA propone rápido; verificar sigue siendo trabajo humano de dirigir

Cada pieza de este proyecto se probó de verdad antes de darse por buena —
no "se ve bien en el código", sino ejecutada:

- Cada endpoint se probó con `curl` real contra el Apache/MySQL de XAMPP
  corriendo (login válido e inválido, intento de suplantación, borrado sin
  permiso, filtros de visibilidad) — no una lectura del código asumiendo
  que hace lo que dice.
- El resumen mensual se verificó con un cálculo independiente en Python
  (`scripts/verify_summary.py`) en vez de confiar en que "la consulta SQL
  se ve correcta".
- El frontend se probó tomando capturas de pantalla reales del navegador ya
  autenticado (vía Chrome DevTools Protocol, headless), no solo revisando
  el HTML/CSS.

Esto no habría pasado solo, la IA no ejecuta pruebas por iniciativa propia
salvo que se le pida — pedirlo explícitamente ("pruébalo de verdad, no
solo escribas el código") fue lo que lo activó.

### 8.2 Encontrar errores de la IA por evidencia, no por sospecha

Cuatro ejemplos concretos de esta misma sesión:

1. **El gráfico de barras del resumen** se veía con las tres barras casi
   idénticas en una captura de pantalla, aunque representaban 100%, 54% y
   23%. Se comparó el ancho real calculado en el DOM (`getComputedStyle`)
   contra lo que se veía en la imagen — la lógica JS estaba bien, el
   problema era puramente de contraste de color en el CSS.
2. **El script `database/seed.sql`** funcionaba perfecto por línea de
   comandos, pero falló al usarlo de verdad en phpMyAdmin — ver el caso
   completo en **§5.1 de este documento**. Fue un aprendizaje muy
   concreto: **"funciona en mi prueba" y "funciona en la herramienta real
   que va a usar la otra persona" no son la misma afirmación.**
3. **El bloqueo por intentos fallidos de login** simplemente no
   bloqueaba, sin ningún error visible — solo dejaba de tener efecto. La
   causa fue un desfase de 8 horas entre el reloj de PHP y el de MySQL en
   la misma máquina; ver el caso completo en **§5.2**. El error más
   difícil de encontrar no siempre es el que lanza una excepción — a
   veces es el que falla en silencio.
4. **Los acentos corruptos en pantalla** (`§5.4`) los encontró la persona
   probando la aplicación en su propio navegador, no las pruebas
   automatizadas ni las capturas de pantalla que se tomaron durante la
   sesión — ninguna de esas comprobaciones comparaba el texto exacto
   carácter por carácter, solo la estructura de la respuesta. Es la
   prueba más clara de todo el proyecto de que revisar con evidencia
   automatizada reduce el riesgo, pero no reemplaza que un humano abra
   la aplicación real al final.

### 8.3 Pedir explícitamente lo que no es "solo código"

Instrucciones que cambiaron el resultado, en orden de cuándo se pidieron:

- *"Documenta en español e inglés"* — obligó a una convención consistente
  (`ES: ... / EN: ...`) revisable con una sola búsqueda de texto en todo
  el proyecto, no una promesa vaga de "está bilingüe".
- *"Verifica el número antes de confiar en él"* (la instrucción original
  del ejercicio) — llevó a construir `verify_summary.py` como una pieza
  aparte del sistema, no como un test dentro del mismo backend.
- *"El código ya está limpio, ¿todo?"* — disparó una auditoría real
  (`php -l` en cada archivo PHP, `node --check` en cada JS, búsqueda de
  `var_dump`/`console.log`/`TODO` olvidados) en vez de una respuesta de
  confianza sin evidencia — y esa auditoría sí encontró un archivo
  (`schema.sql`) con el formato bilingüe antiguo, que se corrigió al
  momento.

### 8.4 Las decisiones de negocio abiertas no las decide la IA sola

El enunciado deja dos reglas de negocio abiertas a propósito (traslapes de
horario, visibilidad del resumen entre consultores). La IA propuso una
decisión para cada una, pero **con una justificación explícita y
verificable** (`NOTES.md` §2), no como una regla arbitraria — el criterio
para aceptar una decisión de este tipo no debería ser "la IA lo escribió",
sino "la razón que da se sostiene si alguien la cuestiona en la
entrevista". Vale la pena poder explicar *en las propias palabras* por qué
cada una tiene sentido, no solo repetir el texto.

---

## 9. ES: Checklist — qué se puede defender en una entrevista / EN: Interview-readiness checklist

| Si preguntan... | La respuesta está en... |
|---|---|
| "¿Por qué no usaste el repositorio de partida que te dieron?" | `NOTES.md` (aclaración de contexto, al inicio) — decisión explícita: el entrevistador pidió no copiar y pegar, así que todo el código de este proyecto se escribió desde cero, en un stack elegido por mí (PHP + MySQL en vez de Node.js + SQLite), precisamente para que cada línea se pueda defender como propia |
| "¿Cómo evitas que un consultor vea/borre datos de otro?" | `NOTES.md` §1.1, §1.2 + `backend/includes/auth.php` |
| "¿Cómo sabes que el resumen mensual es correcto?" | `scripts/verify_summary.py` + `NOTES.md` §4 (evidencia real de una corrida) |
| "¿Por qué no usaste un framework?" | §1 y §3 de este documento — tamaño del proyecto vs. complejidad agregada |
| "¿Qué pasa con las horas traslapadas?" | `NOTES.md` §2.1 — decisión justificada, no solo implementada |
| "¿Por qué Python para el script de verificación, si el backend es PHP?" | §1 de este documento — verificación independiente real, no solo "porque sí" |
| "¿Cómo protegiste contra CSRF/XSS/inyección SQL?" | `NOTES.md` §1.3, §1.6, §1.9 |
| "¿Por qué está todo en `public/`, `database/`, `backend/`...?" | `docs/DOCUMENTACION.md` §2 + `NOTES.md` §1.12 |
| "¿Por qué tantos commits chicos en vez de uno?" | `docs/GIT.md` completo |
| "Cuéntame de un bug real que hayas resuelto" | §5.1 (error `#1701` de phpMyAdmin), §5.2 (desfase de reloj PHP/MySQL), §5.3 (PowerShell + `2>&1`) y §5.4 (acentos corruptos por codificación) — los cuatro con causa raíz y corrección, no solo el síntoma |
| "¿Las pruebas automatizadas lo detectan todo?" | §5.4 y §8.2 (punto 4) — un caso real donde no fue así, y qué se hizo distinto después |
| "¿Tienes pruebas automatizadas, o todo fue manual?" | `scripts/test_api.py` (15 pruebas, `unittest`) + `scripts/check_all.sh`/`.ps1` — §8.2 y §8.3 de `docs/DOCUMENTACION.md` |
| "¿Cómo evitas fuerza bruta en el login?" | `NOTES.md` §1.7 — bloqueo por usuario persistido en BD, no por sesión de navegador |
