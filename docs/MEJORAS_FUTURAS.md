# Mejoras futuras — ConsultHours

Esto es lo que **no** se implementó a propósito (para no sobre-diseñar un
ejercicio técnico), pero sí implementaría si este proyecto pasara a ser un
sistema real en producción. Cada una con su justificación — ver también
`docs/APRENDIZAJES.md` sobre por qué la restricción también es una decisión
de ingeniería válida.

## 1. Edición de registros

Hoy solo se puede crear y borrar — para corregir un dato hay que borrar y
volver a capturar, perdiendo el registro de que hubo un error. Agregaría un
endpoint `PUT /api/records.php` con la misma regla de autorización que
borrar (dueño o admin), y guardaría quién y cuándo lo editó.

## 2. Historial de auditoría (quién cambió qué)

Al ser datos de facturación, no solo importa el dato actual sino quién lo
creó/editó/borró y cuándo. Agregaría una tabla `audit_log` (acción, tabla,
registro, usuario, timestamp, valores antes/después) — útil para resolver
disputas de facturación con un cliente.

## 3. Exportar el resumen mensual (CSV / Excel)

El resumen mensual hoy solo se ve en pantalla. Para que sirva de verdad
para facturar, un admin necesitaría descargarlo — un botón que genere un
CSV desde `/api/summary.php` es una mejora pequeña con mucho valor real.

## 4. Paginación en "Registros"

Con 20 registros de prueba no hace falta, pero una consultora real
acumula miles. Cargar todo de un jalón dejaría de ser rápido. Agregaría
paginación (`LIMIT`/`OFFSET` o por cursor) en `GET /api/records.php`
cuando el volumen lo justifique — no antes, para no complicar el código
por un problema que hoy no existe.

## 5. Rate limiting también por IP (a nivel de infraestructura)

El bloqueo de login actual protege una **cuenta** (por usuario, ver
`NOTES.md` §1.7). No protege contra alguien probando muchas cuentas
distintas a la vez desde la misma IP. En producción, agregaría esa capa
aparte (ej. un *reverse proxy* con límite de tasa, o fail2ban) — es
infraestructura, no código de la aplicación.

## 6. Más contexto en la insignia "⚠ traslape" del listado

Desde que crear un registro traslapado se bloquea (ver `NOTES.md` §2.1),
el mensaje de error al capturar ya dice con qué horario exacto choca. Lo
que falta es lo mismo pero en el **listado**: la insignia "⚠ traslape" de
un registro que ya existe (por ejemplo, el ejemplo de seed del 6 de
agosto, que nunca pasó por la validación de creación) solo avisa que hay
un cruce — hay que buscar a ojo entre las filas del mismo día para
encontrar con cuál. Agregaría un tooltip o un enlace que señale
directamente el otro registro.

## 7. Variables de entorno para configuración sensible

`backend/config/database.php` tiene las credenciales de MySQL escritas
directo en el archivo (aceptable para XAMPP local, con `root` sin
contraseña). En un despliegue real, las movería a variables de entorno o
a un archivo `.env` fuera del control de versiones, para no exponer
credenciales reales si el repositorio se hace público.

## 8. HTTPS real

La cookie de sesión hoy tiene `secure: false` a propósito, porque XAMPP
local corre sobre HTTP plano (ver `backend/includes/auth.php`). En
cualquier entorno con dominio propio, activaría `secure: true` y forzaría
HTTPS con un certificado real (Let's Encrypt, por ejemplo).

## 9. Pruebas automatizadas del frontend, no solo del backend

`scripts/test_api.py` prueba el API a fondo, pero nada prueba la
interfaz en sí (que un clic realmente actualice el DOM, por ejemplo).
Agregaría algo tipo Playwright o Cypress — herramientas hechas para eso,
en vez de seguir automatizando pruebas de UI a mano con el DevTools
Protocol como hice para verificar manualmente durante el desarrollo.

## 10. Roles más granulares

Hoy solo hay `admin` y `consultant`. Un caso real de consultora podría
necesitar un rol intermedio (ej. "gerente de cuenta" que ve el resumen de
un grupo de consultores, no todos ni solo el suyo). No lo agregué porque
el enunciado no lo pide y el esquema actual (`ENUM('admin','consultant')`)
ya deja claro dónde extenderlo el día que haga falta — ver
`docs/DOCUMENTACION.md` §10.

---

**Por qué ninguna de estas se hizo ahora:** el ejercicio pide un sistema
sencillo, correcto y bien defendido — no uno con la mayor cantidad de
funciones posible. Cada punto de arriba tiene un "cuándo tendría sentido"
implícito (más usuarios, más datos, un despliegue real) que hoy no aplica.
Agregarlas sin esa necesidad sería complejidad sin beneficio, exactamente
lo que se evitó a propósito en el resto del proyecto.
