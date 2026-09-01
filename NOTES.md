# NOTES — ConsultHours

Notas del ejercicio técnico: vulnerabilidades consideradas y su mitigación,
las dos decisiones de negocio que el enunciado deja abiertas, y el uso de
IA durante la construcción del proyecto.

> Aclaración de contexto: el enunciado original describe un repositorio de
> partida (`backend/`, `frontend/` en Node.js) con vulnerabilidades ya
> sembradas para encontrar y corregir. En este caso el directorio de trabajo
> estaba vacío, así que el sistema se construyó **desde cero** en PHP +
> MySQL (XAMPP) + JS, por decisión explícita para este ejercicio. Por eso,
> en vez de un diff "antes/después" de un código ajeno, este documento lista
> los riesgos de seguridad típicos de un sistema de este tipo (autenticación
> + autorización por rol/dueño + entrada de usuario) que se identificaron
> **durante el diseño** y cómo quedaron mitigados en el código, con
> referencia a archivo y mecanismo concreto.

---

## 1. Seguridad: riesgos identificados y su mitigación

### 1.1 Suplantación del dueño del registro (el bug explícito del enunciado)

**Riesgo:** si el endpoint de creación de un registro de horas confía en un
campo `consultant_id` / `user_id` enviado por el propio cliente (body del
POST), cualquier usuario autenticado podría crear — y por lo tanto facturar
— horas a nombre de **otro** consultor.

**Mitigación:** en [`backend/api/records.php`](backend/api/records.php)
(`handle_create`), el dueño del registro se toma **siempre** de
`$user['id']`, es decir de la sesión activa del servidor. El body JSON que
manda el cliente nunca se lee para ese campo — ni siquiera existe un
`consultant_id` esperado en el payload de creación. Se probó explícitamente
enviando `{"consultant_id": 3, ...}` autenticado como `carla` (id 2): el
registro se creó a nombre de `carla`, ignorando el valor suplantado (ver
prueba en la sección de verificación más abajo).

### 1.2 Autenticación vs. autorización como dos capas independientes

**Riesgo:** proteger un endpoint solo con "¿hay sesión iniciada?" no evita
que un usuario autenticado actúe sobre datos que no le pertenecen (por
ejemplo, borrar el registro de otro consultor).

**Mitigación:** `backend/includes/auth.php` separa explícitamente:
- `require_auth()` — autenticación: exige sesión iniciada (401 si no hay).
- `require_admin()` — autorización por rol (403 si no es admin).
- `can_manage_record($user, $recordConsultantId)` — autorización por dueño:
  un consultor solo puede administrar sus propios registros; un admin,
  cualquiera.

`backend/api/records.php` (`handle_delete`) aplica `can_manage_record()`
antes de borrar. `backend/api/summary.php` y `backend/api/records.php`
(`handle_list`) aplican la misma lógica para **lectura**, no solo
escritura (ver decisión de negocio §2.2).

### 1.3 Inyección SQL

**Mitigación:** toda consulta usa **PDO con sentencias preparadas y
parámetros enlazados** (`PDO::ATTR_EMULATE_PREPARES => false` en
`backend/config/database.php`, para forzar sentencias preparadas reales del
lado de MySQL). Ningún valor de usuario se concatena en SQL, incluida la
búsqueda de texto libre (`LIKE ?` con parámetro, no `LIKE '%$q%'`).

### 1.4 Contraseñas

**Mitigación:** `database/seed.php` genera los hashes con
`password_hash()` (bcrypt); `backend/api/auth/login.php` verifica con
`password_verify()`. La contraseña en texto plano nunca se guarda, ni se
compara con `===`, ni se registra en logs. `password_verify()` se ejecuta
también contra un hash "dummy" cuando el usuario no existe, para no dar
pistas de *timing* sobre qué usuarios son válidos.

### 1.5 Fijación de sesión y cookies de sesión débiles

**Mitigación:** `backend/includes/auth.php` (`start_secure_session`)
configura la cookie de sesión con `HttpOnly` (JS no puede leerla) y
`SameSite=Lax`. En `login.php`, tras validar credenciales se llama
`session_regenerate_id(true)` para invalidar cualquier ID de sesión previo
a la autenticación (mitiga *session fixation*).

### 1.6 CSRF en un API basado en cookies

**Riesgo:** al usar cookies de sesión, un sitio malicioso podría inducir al
navegador de un usuario ya autenticado a mandar un POST/DELETE sin que el
usuario lo sepa.

**Mitigación:** `require_same_origin_header()` en
`backend/includes/functions.php` exige el encabezado personalizado
`X-Requested-With: ConsultHours` en toda petición que cambia estado (POST,
DELETE). Un `<form>` HTML clásico no puede fijar ese encabezado, y una
petición cruzada por `fetch`/XHR que sí lo intente dispara un *preflight*
CORS que este servidor rechaza (no se envían cabeceras
`Access-Control-Allow-Origin`). Se combina con `SameSite=Lax` (§1.5) como
defensa en profundidad.

### 1.7 Fuerza bruta en login

**Mitigación (parcial, documentada como limitación):**
`backend/api/auth/login.php` bloquea 30 segundos tras 5 intentos fallidos
**dentro de la misma sesión de navegador**. No sustituye un *rate limit*
real por IP a nivel de infraestructura (ej. fail2ban, un *reverse proxy*
con límite de tasa, o una tabla de intentos por IP con expiración) — para
un sistema en producción se recomienda esa capa adicional.

### 1.8 Fuga de información en errores

**Riesgo:** un error de PHP no controlado (excepción de PDO, *notice*, etc.)
puede mostrar al cliente rutas del servidor, la consulta SQL exacta o la
versión de PHP.

**Mitigación:** `backend/includes/bootstrap.php` desactiva
`display_errors`, registra todo con `error_log()`, e instala un
`set_exception_handler` / `set_error_handler` que siempre responde con un
mensaje genérico (`json_error('Ocurrió un error interno...', 500)`).

### 1.9 XSS al mostrar datos del backend

**Mitigación:** el frontend nunca inserta texto de usuario (descripciones,
nombres) directamente como HTML. `frontend/assets/js/auth.js`
(`escapeHtml`) escapa todo antes de insertarlo en el DOM, como defensa en
profundidad además de la validación del servidor.

### 1.10 Validación de entrada

**Mitigación:** `backend/api/records.php` (`handle_create`) valida
formato de fecha/hora, que `start_time < end_time`, que el cliente exista,
longitud máxima de la descripción, y que las horas calculadas estén en un
rango razonable (`0 < horas <= 24`), todo del lado del servidor (la
validación del navegador —`required`, `type="time"`— es solo UX, nunca la
única barrera).

### 1.11 Same-origin en vez de CORS abierto

**Decisión de diseño:** en lugar de exponer el API con CORS permisivo
(`Access-Control-Allow-Origin: *` + credenciales, una combinación insegura
muy común en este tipo de ejercicios), el frontend y el backend se sirven
desde el **mismo origen** (`index.php` → `frontend/index.html`, que llama a
`../backend/api/...`). Esto elimina la necesidad de CORS por completo y
reduce la superficie de ataque.

---

## 2. Decisiones de negocio (a propósito abiertas en el enunciado)

### 2.1 Horas que se traslapan el mismo día (ejemplo real: 6 de agosto)

En los datos de prueba, `carla` tiene dos registros el 2026-08-06: uno en
Acme Corp de 09:00–12:00 y otro en Globex Industries de 11:00–13:00 (se
traslapan de 11:00 a 12:00).

**Decisión: no bloquear el registro traslapado; detectarlo y marcarlo
visualmente como advertencia**, tanto en la respuesta de creación
(`backend/api/records.php::handle_create`, campo `warning`) como en el
listado (`mark_overlaps()`, campo `overlaps` por registro, mostrado en la
UI como una insignia "⚠ traslape").

**Por qué:**
- Un consultor puede tener razones legítimas para registrar horas
  traslapadas: cambio rápido de contexto entre dos clientes, una llamada
  que efectivamente se solapó con otra reunión, o una corrección posterior
  de un registro que aún no ha borrado el original.
- Bloquear por completo el registro (rechazar el POST) le quita al
  consultor la capacidad de dejar constancia de lo que realmente pasó, y
  convierte un problema de **datos que hay que revisar** en un problema de
  **flujo de trabajo roto**.
- La alternativa de "recortar" automáticamente las horas para que no se
  traslapen es peor: decide silenciosamente cuál de los dos registros es
  el "correcto", lo cual no le corresponde al sistema.
- En cambio, marcar y dejar visible el traslape (tanto al propio consultor
  como a un admin que revise el resumen) da la información necesaria para
  que un humano decida — que es lo que de hecho pide el ejercicio al
  incluir este caso en los datos de prueba, en vez de simplemente prohibirlo.

Si el negocio decidiera después que ciertos traslapes sí deben bloquearse
(p. ej. mismo cliente, mismo horario exacto = probable doble captura por
error), el punto de extensión ya existe: `mark_overlaps()` /  el `SELECT`
de traslape en `handle_create()` son el lugar natural para añadir esa regla
más estricta sin tocar el resto del sistema.

### 2.2 Visibilidad del resumen financiero de otros consultores

**Decisión: un consultor solo ve su propio resumen mensual facturable (y
solo sus propios registros en el listado); solo un administrador puede ver
el resumen agregado de todos los consultores, o filtrar por uno específico**
(`backend/api/summary.php` y `backend/api/records.php::handle_list`).

**Por qué:**
- Las horas facturables están directamente ligadas a cuánto se le cobra a
  un cliente y, indirectamente, al desempeño/productividad de cada
  consultor — es información sensible en el mismo sentido que un salario o
  una evaluación de desempeño, no un dato operativo neutro.
- El enunciado ya establece un precedente equivalente para el borrado
  ("un consultor solo puede eliminar sus propios registros"); extender el
  mismo criterio de dueño a la **lectura** de datos financieros es
  consistente y evita una asimetría rara (no poder borrar el registro de
  un colega, pero sí poder ver cuánto le factura a cada cliente).
- Un administrador sí necesita la vista agregada — es quien reporta
  facturación consolidada — así que la restricción es por rol, no
  absoluta.
- Alternativa considerada y descartada: permitir que cualquier consultor
  vea el resumen de cualquier otro. Se descartó porque no aporta valor al
  consultor (no necesita el dato para hacer su trabajo) y sí introduce un
  riesgo de privacidad/clima laboral sin justificación de negocio clara.

---

## 3. Uso de IA

Este proyecto se construyó con **Claude Code** como asistente de
programación, en una sesión interactiva. Resumen de lo que se le pidió y lo
que hubo que ajustar del resultado:

**Lo que se pidió:** construir el sistema completo descrito en el
enunciado (backend PHP + MySQL sobre XAMPP, frontend HTML/CSS/JS con
animaciones 3D, código comentado en español e inglés, un script de Python
que verifique el resumen mensual contra los datos semilla, documentación
final en español), incluyendo las dos decisiones de negocio abiertas y sus
justificaciones.

**Cómo se dirigió el trabajo:**
- Antes de escribir código, se le pidió a la IA que confirmara dos cosas
  ambiguas en la petición original: si debía **construir el sistema** o
  solo entregar una lista de prompts, y qué rol debía tener Python en un
  stack que ya incluía PHP/MySQL/JS. Se resolvió: construir el sistema
  completo, con Python limitado al script de verificación (no como parte
  del backend).
- Se le pidió explícitamente **probar el sistema real**, no solo escribir
  el código: correr `schema.sql` y `seed.php` contra el MySQL de XAMPP,
  probar cada endpoint con `curl` (incluyendo los intentos de suplantación
  y de acceso no autorizado), correr el script de Python contra el API
  corriendo de verdad, y tomar capturas de pantalla del frontend ya
  autenticado (vía Chrome DevTools Protocol en modo headless) para
  confirmar visualmente que la interfaz y las animaciones funcionan.

**Qué hubo que corregir del resultado inicial de la IA:**
- El primer diseño de las barras del gráfico de horas por cliente
  (`frontend/assets/css/style.css`, `.chart-track` / `.chart-fill`) tenía
  muy poco contraste entre la barra de fondo y la barra de progreso — al
  revisar la captura de pantalla, las tres barras (100%, 54% y 23% de
  ancho) se veían casi idénticas. Se detectó comparando el ancho calculado
  en el DOM (vía DevTools Protocol) contra lo que realmente se veía en la
  imagen, y se corrigió oscureciendo el fondo del track y agregando un
  resplandor (`box-shadow`) a la barra de relleno.
- El primer boceto del entrypoint (`index.php`) intentaba incluir
  (`include`) el HTML del frontend directamente. Se cambió a una
  redirección HTTP simple, porque incluir el archivo mantiene la URL del
  navegador en la raíz del proyecto mientras el HTML incluido usa rutas
  relativas pensadas para vivir un nivel más abajo (`frontend/`) — eso
  rompe todos los `<link>`/`<script>` y las llamadas al API. La redirección
  evita el problema por completo sin necesitar rutas absolutas ni lógica
  de resolución de rutas en PHP.
- Antes de dar por buena la respuesta de `/api/summary.php`, se generaron a
  mano (no con la IA "adivinando") los totales esperados por cliente y por
  mes a partir de `seed_data.json`, y se contrastaron contra la salida real
  del script de Python — ver [`scripts/verify_summary.py`](scripts/verify_summary.py)
  y el resultado en la sección siguiente. Esto es, en sí mismo, la
  respuesta a "no confíes en el número que regresa el endpoint sin
  verificarlo": se verificó con un cálculo independiente, no repitiendo la
  misma lógica del backend.

---

## 4. Evidencia de verificación

Salida real de `python scripts/verify_summary.py` contra el backend
corriendo en XAMPP con los datos de `database/seed_data.json` (20
registros, 3 usuarios, 4 clientes):

```
Verificando /api/summary.php contra seed_data.json
Backend: http://localhost/PRUEBA%20TECNICA/backend/api
Meses encontrados en los datos de prueba: 2026-07, 2026-08

  [OK      ] carla (2026-07)          Acme Corp            esperado=  9.00h   api=  9.00h
  [OK      ] carla (2026-07)          Globex Industries    esperado=  8.00h   api=  8.00h
  [OK      ] carla (2026-08)          Acme Corp            esperado= 11.00h   api= 11.00h
  [OK      ] carla (2026-08)          Globex Industries    esperado=  6.00h   api=  6.00h
  [OK      ] carla (2026-08)          Umbrella Labs        esperado=  2.50h   api=  2.50h
  [OK      ] miguel (2026-07)         Initech Solutions    esperado=  8.00h   api=  8.00h
  [OK      ] miguel (2026-07)         Umbrella Labs        esperado=  3.00h   api=  3.00h
  [OK      ] miguel (2026-08)         Initech Solutions    esperado=  7.00h   api=  7.00h
  [OK      ] miguel (2026-08)         Umbrella Labs        esperado= 10.00h   api= 10.00h
  [OK      ] admin/todos (2026-07)    Acme Corp            esperado=  9.00h   api=  9.00h
  [OK      ] admin/todos (2026-07)    Globex Industries    esperado=  8.00h   api=  8.00h
  [OK      ] admin/todos (2026-07)    Initech Solutions    esperado=  8.00h   api=  8.00h
  [OK      ] admin/todos (2026-07)    Umbrella Labs        esperado=  3.00h   api=  3.00h
  [OK      ] admin/todos (2026-08)    Acme Corp            esperado= 11.00h   api= 11.00h
  [OK      ] admin/todos (2026-08)    Globex Industries    esperado=  6.00h   api=  6.00h
  [OK      ] admin/todos (2026-08)    Initech Solutions    esperado=  7.00h   api=  7.00h
  [OK      ] admin/todos (2026-08)    Umbrella Labs        esperado= 12.50h   api= 12.50h

Todos los resúmenes de /api/summary.php coinciden con seed_data.json.
```

Pruebas de autorización realizadas manualmente vía `curl` (ver también
`docs/DOCUMENTACION.md`):

- Sin sesión, `GET /backend/api/records.php` → `401`.
- `carla` autenticada creando un registro con `"consultant_id": 3` (el id
  de `miguel`) en el body → el registro queda a nombre de `carla` (id 2),
  el valor suplantado se ignora.
- `carla` autenticada intentando `DELETE` sobre un registro de `miguel` →
  `403`.
- `carla` autenticada intentando pasar `?consultant_id=3` en
  `/api/summary.php` o `/api/records.php` → se ignora, sigue viendo solo
  lo suyo.
- `admin` autenticado en `/api/summary.php` sin filtro → agregado de todos
  los consultores; con `?consultant_id=`, solo ese consultor.
