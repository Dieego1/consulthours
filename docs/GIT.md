# Control de versiones (Git) / Version control (Git)

## ES: ¿Qué es Git y por qué hay un repositorio aquí?

**Git no es una función del programa ConsultHours.** ConsultHours (la
aplicación web: PHP + MySQL + HTML/CSS/JS) funciona exactamente igual
exista o no un repositorio git — puedes borrar la carpeta `.git/` por
completo y la aplicación sigue corriendo sin ningún cambio, porque git
nunca se ejecuta en tiempo de ejecución del programa; es una herramienta
externa que solo se usa mientras se **escribe y entrega** el código.

Git es un sistema de control de versiones: guarda una fotografía (un
"commit") del estado completo del código cada vez que se le pide, con
quién hizo el cambio, cuándo, y un mensaje explicando qué y por qué. Sirve
para tres cosas en este proyecto:

1. **Es un requisito explícito del ejercicio.** El enunciado original dice
   textualmente: *"Sube tu solución con commits incrementales normales (no
   un solo commit final)"*. Es decir, quien revisa este ejercicio no solo
   quiere ver el resultado final, quiere ver **el proceso**: en qué orden
   se construyó, si el backend se hizo antes que el frontend, si la
   seguridad se pensó desde el diseño o se agregó después, etc. Un único
   commit gigante no permite ver nada de eso.
2. **Documenta el "por qué" de cada pieza**, no solo el "qué". Cada mensaje
   de commit en este repositorio explica la intención del cambio (ver la
   tabla abajo) — es historia consultable, a diferencia de un comentario
   en el código que solo describe el estado actual.
3. **Permite deshacer con seguridad.** Si algo se rompe, se puede comparar
   contra un commit anterior (`git diff`) o volver a él (`git checkout`)
   sin depender de una copia de respaldo manual.

## EN: What is Git and why is there a repository here?

**Git is not a feature of the ConsultHours program.** ConsultHours (the
web app: PHP + MySQL + HTML/CSS/JS) works exactly the same whether or not
a git repository exists — you could delete the `.git/` folder entirely and
the application keeps running unchanged, because git is never executed at
the program's runtime; it's an external tool only used while the code is
being **written and delivered**.

Git is a version-control system: it saves a snapshot (a "commit") of the
full state of the code whenever asked, along with who made the change,
when, and a message explaining what and why. It serves three purposes in
this project:

1. **It's an explicit requirement of the exercise.** The original prompt
   literally says: *"Submit your solution with normal incremental commits
   (not a single final commit)."* In other words, whoever reviews this
   exercise doesn't just want to see the final result — they want to see
   **the process**: in what order it was built, whether the backend came
   before the frontend, whether security was designed in from the start or
   bolted on later, etc. One giant commit wouldn't show any of that.
2. **It documents the "why" of each piece**, not just the "what." Every
   commit message in this repository explains the intent behind the
   change (see the table below) — that's searchable history, unlike a
   code comment, which only describes the current state.
3. **It allows safely undoing changes.** If something breaks, you can
   compare against an earlier commit (`git diff`) or go back to it
   (`git checkout`) without relying on a manual backup copy.

---

## ES: Cómo revisar el historial / EN: How to review the history

```bash
git log --oneline              # ES: lista compacta de todos los commits / EN: compact list of every commit
git log -p -- backend/api/records.php   # ES: qué cambió, archivo por archivo / EN: what changed, file by file
git show <hash>                # ES: el contenido completo de un commit / EN: the full content of one commit
git diff <hash1> <hash2>       # ES: diferencia entre dos puntos del historial / EN: difference between two points in history
```

## ES: Los commits de este proyecto, explicados / EN: This project's commits, explained

Orden cronológico real (el más antiguo primero). El mensaje corto es el
que aparece en `git log`; la columna de la derecha traduce/explica la
intención para quien no lea español.

| # | Commit (mensaje corto, tal como aparece en `git log`)                          | EN — what it did and why |
|---|----------------------------------------------------------------------------------|---------------------------|
| 1 | `chore: estructura inicial del proyecto y guia de instalacion`                   | Bootstrapped the repo: `.gitignore` and the initial `README.md` with setup instructions — before any actual code, so the very first commit already explains how to run what follows. |
| 2 | `feat: esquema de base de datos MySQL y datos de prueba`                         | Added `database/schema.sql` (tables), `seed_data.json` (the single source of truth for test data) and `seed.php` (loads that JSON into MySQL). Data model first, because everything else depends on it. |
| 3 | `feat(backend): conexion PDO y capa de autenticacion/autorizacion`               | Added the shared backend building blocks *before* any endpoint: the PDO connection, JSON response helpers, CSRF check, and — the core of the exercise's security ask — `require_auth()` vs. `require_admin()`/`can_manage_record()` as two independent layers. |
| 4 | `feat(backend): endpoints de autenticacion (login/logout/session)`               | The first real endpoints: login (with brute-force throttling and session-fixation protection), logout, and session check. Nothing else in the API works without these. |
| 5 | `feat(backend): catalogos de clientes y consultores`                             | Small read-only endpoints (`clients.php`, `consultants.php`) needed to populate dropdowns before the more complex endpoints that depend on them existing. |
| 6 | `feat(backend): CRUD de registros de horas con autorizacion por dueno`            | The heart of the exercise: create/list/delete time records, with the owner-impersonation bug fixed by design (`consultant_id` always from the session) and the overlap-warning business decision implemented. |
| 7 | `feat(backend): resumen mensual de horas facturables por cliente`                | The `/api/summary.php` endpoint the exercise centers on, plus the visibility business decision (consultants see only their own summary). |
| 8 | `feat: punto de entrada principal (index.php)`                                  | The root PHP entry point (later moved to `public/` — see the commits from this second working session, listed further down). |
| 9 | `feat(frontend): estructura HTML y estilos con animaciones 3D`                   | The static shell of the UI: `index.html`, `style.css`, `animations.css` — structure and visuals before behavior. |
| 10 | `feat(frontend): logica de la SPA en JavaScript vanilla`                        | The JS that makes the HTML from commit 9 actually work: `api.js`, `auth.js`, `records.js`, `summary.js`, `main.js`. |
| 11 | `feat: script de verificacion independiente del resumen mensual (Python)`        | `scripts/verify_summary.py` — built *after* the summary endpoint existed, specifically to check it against `seed_data.json` rather than trust it blindly (as the exercise asks). |
| 12 | `docs: notas de seguridad, decisiones de negocio y documentacion tecnica`        | `NOTES.md` and `docs/DOCUMENTACION.md`, first version, once there was a finished, tested system to document truthfully instead of documenting intentions. |

### ES: Segunda sesión de trabajo — reorganización, phpMyAdmin y documentación adicional
### EN: Second working session — reorganization, phpMyAdmin, and further documentation

Estos commits llegaron después de una revisión pidiendo mover archivos,
dar una vía de importación por phpMyAdmin, y documentar más a fondo.

These commits came after a review round asking to move files around,
provide a phpMyAdmin import path, and document things more thoroughly.

| # | Commit (mensaje corto, tal como aparece en `git log`) | EN — what it did and why |
|---|-----------------------------------------------------------|---------------------------|
| 13 | `docs: estandariza comentarios bilingues ES/EN en CSS y HTML` | `animations.css`, `style.css` and `index.html` used a "Spanish / English" one-line format instead of the `ES: ... / EN: ...` label convention used everywhere else — standardized so a single text search (`grep "ES:"`/`"EN:"`) can verify bilingual coverage across the *entire* project the same way. |
| 14 | `feat: mueve index.php a public/ y protege carpetas internas` | Moved the entry point from the project root into `public/index.php` (a root `.htaccess` with `DirectoryIndex public/index.php` keeps the same URL working). Also added `Require all denied` to `database/`, `scripts/`, `backend/config/`, `backend/includes/` after confirming with `curl` that `database/seed_data.json` (plaintext test passwords) was publicly downloadable — `200` before, `403` after. |
| 15 | `feat(database): agrega seed.sql para importar directo en phpMyAdmin` | Added a pure-SQL dump (schema + data, passwords already bcrypt-hashed) for pasting directly into phpMyAdmin's SQL tab, as an alternative to the command-line path. |
| 16 | `docs: actualiza README y documentacion tecnica con la nueva estructura` | Updated `README.md`, `NOTES.md` and `docs/DOCUMENTACION.md` to reflect the `public/` move, the `seed.sql` option, and the new `.htaccess` protections. |
| 17 | `docs: explica en espanol e ingles por que este proyecto usa git` | Added this file (`docs/GIT.md`) — git is a delivery/versioning tool, not a feature of the running app, and the exercise explicitly asks for incremental commits. |
| 18 | `fix: estandariza comentarios bilingues en schema.sql` | Found during a "is the code actually clean?" audit: `database/schema.sql` still used the old bilingual format from before commit 13 standardized the rest of the project. Fixed for full consistency. |
| 19 | `docs: documenta el bug de phpMyAdmin/TRUNCATE en el uso de IA` | Added a concrete example to `NOTES.md` §3 (AI usage): the first `seed.sql` worked from the command line but failed in real phpMyAdmin because its "enable foreign key checks" checkbox overrides a plain `SET FOREIGN_KEY_CHECKS=0`. Fixed by removing the need for `TRUNCATE` entirely rather than patching around the checkbox. |
| 20 | `docs: agrega documentacion de aprendizajes del proyecto` | Added `docs/APRENDIZAJES.md`: what each language/tool was for and why, the security/architecture/database/frontend concepts this project demonstrates, and a dedicated section on what working with an AI assistant (Claude Code) on this project actually looked like. |
| 21 | `docs: completa la tabla de commits en GIT.md y enlaza APRENDIZAJES.md` | Extended the commit table above to cover the full history instead of stopping at #12, and linked the new learnings doc from `README.md`. |
| 22 | `feat(frontend): aclara usuario/contrasena en el login y los hace clicables` | The login screen showed test credentials as `admin/admin123` with no visual way to tell username from password apart — now each one is explicitly labeled ("Usuario:"/"Contraseña:") and clickable to autofill the form (keyboard-accessible too). |
| 23 | `docs: agrega caso real del error de base de datos a APRENDIZAJES.md` | Added a dedicated case-study section to `docs/APRENDIZAJES.md` narrating the phpMyAdmin `#1701` error end to end: what happened, the real root cause, how it was diagnosed, and how it was fixed at the source instead of patched around. |
| 24 | `docs: agrega los ultimos 3 commits a la tabla de GIT.md` | Kept this table current with the real history up through #21–23. |
| 25 | `feat(frontend): indicador de scroll horizontal en la tabla (movil)` | Found by testing the layout at a 375px width: the records table could actually be scrolled sideways, but nothing on screen hinted at it. Added a fade gradient (`.scroll-fade-right`) that shows/hides based on real scroll position. |
| 26 | `feat(backend): bloqueo de login persistido en BD, no en sesion` | Replaced the session-based failed-login counter (which reset if you cleared cookies) with a `login_attempts` table, keyed by username. Found and fixed a real bug along the way: PHP's and MySQL's clocks were ~8 hours apart on this machine, so the lockout never triggered — fixed by doing all the time math inside MySQL itself. |
| 27 | `feat: suite de pruebas automatizadas del API (scripts/test_api.py)` | 15 `unittest` tests turning every manual `curl` check from this whole session (impersonation, ownership, overlap detection, summary visibility, SQL injection, rate limiting) into a permanent, repeatable regression suite. |
| 28 | `feat: automatizacion -- un comando que corre todas las verificaciones` | `scripts/check_all.sh`/`.ps1`: one command running PHP/JS linting plus both Python suites. The PowerShell version needed a workaround for a real PowerShell 5.1 quirk (`2>&1` on a native exe corrupts `$LASTEXITCODE`). |
| 29 | `docs: documenta las mejoras de esta ronda (login, pruebas, movil)` | Updated `NOTES.md`, `README.md`, and `docs/APRENDIZAJES.md` to cover everything in commits 25–28. |

> ES: Para lo que venga después de este commit, esta tabla ya no alcanza
> — corre `git log --oneline` para la lista siempre-actualizada.
>
> EN: For anything after this commit, this table stops being enough —
> run `git log --oneline` for the always-current list.

## ES: Por qué el orden importa / EN: Why the order matters

El orden de los commits no es arbitrario: cada uno es algo que **compila y
funciona por sí solo** en el punto en que se hizo (no hay un commit "WIP" a
medio romper en medio de la lista). Esto es intencional — permite que
alguien revise el historial y confirme, en cada paso, que el proyecto
estaba en un estado coherente, en vez de tener que llegar hasta el final
para que algo tenga sentido.

The commit order isn't arbitrary: each one is something that **builds and
works on its own** at the point it was made (there's no half-broken "WIP"
commit in the middle of the list). This is intentional — it lets someone
review the history and confirm, at every step, that the project was in a
coherent state, instead of having to reach the very end before anything
makes sense.
