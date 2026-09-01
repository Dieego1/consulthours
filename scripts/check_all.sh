#!/usr/bin/env bash
# =============================================================================
# ES: Automatización -- un solo comando que verifica todo el sistema de punta
#     a punta: sintaxis de cada archivo PHP, sintaxis de cada archivo JS, la
#     suite de pruebas de la API (scripts/test_api.py) y la verificación
#     independiente del resumen mensual (scripts/verify_summary.py). Es la
#     versión repetible de todas las comprobaciones manuales que se hicieron
#     durante el desarrollo con `curl`/`php -l`/`node --check` una por una.
#
#     Requiere que Apache y MySQL de XAMPP estén corriendo (para las
#     pruebas del API) y python en el PATH.
#
#     Uso:  bash scripts/check_all.sh
#     Termina con código de salida 0 si todo pasó, distinto de 0 si algo
#     falló -- pensado para poder engancharse a un pre-commit hook o a CI
#     el día que este proyecto lo necesite.
#
# EN: Automation -- a single command that verifies the whole system end to
#     end: syntax of every PHP file, syntax of every JS file, the API test
#     suite (scripts/test_api.py), and the independent monthly-summary
#     verification (scripts/verify_summary.py). It's the repeatable version
#     of every manual check done during development with
#     `curl`/`php -l`/`node --check`, one at a time.
#
#     Requires XAMPP's Apache and MySQL to be running (for the API tests)
#     and python on the PATH.
#
#     Usage: bash scripts/check_all.sh
#     Exits 0 if everything passed, non-zero if something failed -- meant
#     to be pluggable into a pre-commit hook or CI the day this project
#     needs one.
# =============================================================================
set -uo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

# ES: Rutas de PHP/Python configurables por variable de entorno, con el
#     valor por defecto de una instalación estándar de XAMPP en Windows.
# EN: PHP/Python paths configurable via environment variable, defaulting
#     to a standard XAMPP install on Windows.
PHP_BIN="${PHP_BIN:-/c/xampp/php/php.exe}"
PYTHON_BIN="${PYTHON_BIN:-python}"

FAILED=0
step() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
ok()   { printf '  \033[0;32m✔ %s\033[0m\n' "$1"; }
fail() { printf '  \033[0;31m✘ %s\033[0m\n' "$1"; FAILED=1; }

# --- 1) Sintaxis de todos los archivos PHP / PHP syntax --------------------
step "1/4 Sintaxis PHP (php -l)"
php_errors=0
while IFS= read -r -d '' f; do
  if ! "$PHP_BIN" -l "$f" > /dev/null 2>&1; then
    fail "$f"
    "$PHP_BIN" -l "$f"
    php_errors=1
  fi
done < <(find . -name "*.php" -not -path "./.git/*" -print0)
[ "$php_errors" -eq 0 ] && ok "Todos los archivos .php tienen sintaxis válida"

# --- 2) Sintaxis de todos los archivos JS / JS syntax -----------------------
step "2/4 Sintaxis JavaScript (node --check)"
js_errors=0
while IFS= read -r -d '' f; do
  if ! node --check "$f" > /dev/null 2>&1; then
    fail "$f"
    node --check "$f"
    js_errors=1
  fi
done < <(find . -name "*.js" -not -path "./.git/*" -print0)
[ "$js_errors" -eq 0 ] && ok "Todos los archivos .js tienen sintaxis válida"

# --- 3) Suite de pruebas del API / API test suite ---------------------------
step "3/4 Pruebas del API (scripts/test_api.py)"
if "$PYTHON_BIN" scripts/test_api.py > /tmp/check_all_test_api.log 2>&1; then
  ok "$(grep -E '^Ran [0-9]+ tests' /tmp/check_all_test_api.log)"
else
  fail "scripts/test_api.py -- ver detalle abajo"
  cat /tmp/check_all_test_api.log
fi

# --- 4) Resumen mensual contra los datos semilla / Summary vs seed data ----
step "4/4 Resumen mensual vs. seed_data.json (scripts/verify_summary.py)"
if "$PYTHON_BIN" scripts/verify_summary.py > /tmp/check_all_verify_summary.log 2>&1; then
  ok "$(tail -n 1 /tmp/check_all_verify_summary.log)"
else
  fail "scripts/verify_summary.py -- ver detalle abajo"
  cat /tmp/check_all_verify_summary.log
fi

echo ""
if [ "$FAILED" -eq 0 ]; then
  printf '\033[1;32mTodo pasó. El sistema está verificado de punta a punta.\033[0m\n'
else
  printf '\033[1;31mAlgo falló arriba -- revisa el detalle antes de dar por buenos los cambios.\033[0m\n'
fi
exit "$FAILED"
