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
| **Python 3** | Script de verificación independiente (`scripts/verify_summary.py`) que recalcula el resumen mensual y lo compara contra el API real. | **A propósito un lenguaje distinto al del backend.** Si el script de verificación estuviera en PHP y compartiera, aunque fuera sin querer, una función con `summary.php`, un bug en esa función podría "verse bien" en ambos lados. Usar Python obliga a que el cálculo se escriba dos veces, de forma independiente, en dos lenguajes distintos — así una coincidencia entre ambos sí es evidencia real de que el número es correcto. |
| **Bash / PowerShell** | Solo para *probar* el sistema durante el desarrollo (`curl` contra los endpoints, `mysql` por consola) — no son parte de la aplicación entregada. | Verificar con peticiones HTTP reales, no solo leyendo el código, es la única forma de confirmar que la autorización realmente bloquea lo que dice bloquear (ver §8 más abajo). |
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

## 5. ES: Caso real — el error de base de datos que tuve y cómo se corrigió

Este es un caso concreto que pasó en la práctica, no un riesgo teórico —
vale la pena poder contarlo en una entrevista tal cual, con el mensaje de
error real incluido.

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

Dos ejemplos concretos de esta misma sesión:

1. **El gráfico de barras del resumen** se veía con las tres barras casi
   idénticas en una captura de pantalla, aunque representaban 100%, 54% y
   23%. Se comparó el ancho real calculado en el DOM (`getComputedStyle`)
   contra lo que se veía en la imagen — la lógica JS estaba bien, el
   problema era puramente de contraste de color en el CSS.
2. **El script `database/seed.sql`** funcionaba perfecto por línea de
   comandos, pero falló al usarlo de verdad en phpMyAdmin — ver el caso
   completo, con el mensaje de error real y cómo se corrigió, en **§5 de
   este mismo documento**. Fue el aprendizaje más concreto de toda la
   sesión: **"funciona en mi prueba" y "funciona en la herramienta real
   que va a usar la otra persona" no son la misma afirmación.**

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
| "¿Cómo evitas que un consultor vea/borre datos de otro?" | `NOTES.md` §1.1, §1.2 + `backend/includes/auth.php` |
| "¿Cómo sabes que el resumen mensual es correcto?" | `scripts/verify_summary.py` + `NOTES.md` §4 (evidencia real de una corrida) |
| "¿Por qué no usaste un framework?" | §1 y §3 de este documento — tamaño del proyecto vs. complejidad agregada |
| "¿Qué pasa con las horas traslapadas?" | `NOTES.md` §2.1 — decisión justificada, no solo implementada |
| "¿Por qué Python para el script de verificación, si el backend es PHP?" | §1 de este documento — verificación independiente real, no solo "porque sí" |
| "¿Cómo protegiste contra CSRF/XSS/inyección SQL?" | `NOTES.md` §1.3, §1.6, §1.9 |
| "¿Por qué está todo en `public/`, `database/`, `backend/`...?" | `docs/DOCUMENTACION.md` §2 + `NOTES.md` §1.12 |
| "¿Por qué tantos commits chicos en vez de uno?" | `docs/GIT.md` completo |
| "Cuéntame de un bug real que hayas resuelto" | §5 de este documento — el error `#1701` de phpMyAdmin, causa raíz y corrección, no solo el síntoma |
