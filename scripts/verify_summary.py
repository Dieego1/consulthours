#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ES: Script de verificación independiente para /api/summary.php.

    El ejercicio pide explícitamente NO confiar a ciegas en lo que
    devuelve el endpoint de resumen. Este script:
      1) Calcula, en Python, a partir de database/seed_data.json (la
         misma fuente que usa database/seed.php para poblar MySQL) cuántas
         horas facturables debería reportar cada cliente por mes y por
         consultor.
      2) Inicia sesión de verdad contra el backend (como cada consultor y
         como admin) y llama a GET /backend/api/summary.php.
      3) Compara ambos números y reporta cualquier discrepancia.

    No depende de librerías externas (solo la librería estándar de
    Python), para poder ejecutarse con:
        python scripts/verify_summary.py

    Requiere que Apache y MySQL de XAMPP estén corriendo y que
    database/seed.php ya se haya ejecutado.

EN: Independent verification script for /api/summary.php.

    The exercise explicitly asks not to blindly trust whatever the
    summary endpoint returns. This script:
      1) Computes, in Python, from database/seed_data.json (the same
         source database/seed.php uses to populate MySQL) how many
         billable hours each client should report per month and per
         consultant.
      2) Logs in for real against the backend (as each consultant and as
         admin) and calls GET /backend/api/summary.php.
      3) Compares both numbers and reports any discrepancy.

    Has no external dependencies (Python standard library only), so it
    can be run with:
        python scripts/verify_summary.py

    Requires XAMPP's Apache and MySQL to be running and
    database/seed.php to have already been executed.
"""

import argparse
import http.cookiejar
import json
import sys
import urllib.error
import urllib.parse
import urllib.request
from collections import defaultdict
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parent.parent
SEED_PATH = PROJECT_ROOT / "database" / "seed_data.json"

# ES: La URL base se arma a partir del nombre REAL de la carpeta del
#     proyecto en disco (PROJECT_ROOT.name), no de un nombre fijo. Antes
#     decia "PRUEBA%20TECNICA" a mano, que era el nombre de la carpeta
#     durante el desarrollo -- pero quien clone este repositorio de
#     GitHub va a tener una carpeta llamada "consulthours" (el nombre del
#     repo), no esa. Con esto, el script funciona sin tocar nada sin
#     importar cómo se llame la carpeta en la máquina de quien lo corra.
# EN: The base URL is built from the project folder's REAL name on disk
#     (PROJECT_ROOT.name), not a fixed string. It used to hardcode
#     "PRUEBA%20TECNICA", which was this folder's name during
#     development -- but whoever clones this from GitHub will have a
#     folder named "consulthours" (the repo's name), not that one. This
#     way the script works untouched no matter what the folder is named
#     on whoever's machine runs it.
DEFAULT_BASE_URL = f"http://localhost/{urllib.parse.quote(PROJECT_ROOT.name)}/backend/api"

# ES: Evita caracteres corruptos ("mojibake") en la consola de Windows,
#     cuya página de códigos por defecto no siempre es UTF-8.
# EN: Avoids garbled characters ("mojibake") on the Windows console,
#     whose default code page isn't always UTF-8.
try:
    sys.stdout.reconfigure(encoding="utf-8")
except AttributeError:
    pass


def load_seed() -> dict:
    with open(SEED_PATH, encoding="utf-8") as f:
        return json.load(f)


def hours_between(start: str, end: str) -> float:
    """ES: Horas decimales entre dos horas 'HH:MM'. EN: Decimal hours between two 'HH:MM' times."""
    sh, sm = (int(part) for part in start.split(":"))
    eh, em = (int(part) for part in end.split(":"))
    return round(((eh * 60 + em) - (sh * 60 + sm)) / 60, 2)


def expected_summary(seed: dict, month: str, username: str | None) -> dict:
    """
    ES: Horas facturables esperadas por cliente para `month` (YYYY-MM),
        calculadas directamente de seed_data.json. Si `username` es None,
        agrega TODOS los consultores (vista de administrador); si no,
        solo ese consultor.
    EN: Expected billable hours per client for `month` (YYYY-MM),
        computed directly from seed_data.json. If `username` is None,
        aggregates ALL consultants (admin view); otherwise only that
        consultant.
    """
    totals: dict = defaultdict(float)
    for r in seed["time_records"]:
        if not r["date"].startswith(month):
            continue
        if not r["billable"]:
            continue
        if username is not None and r["username"] != username:
            continue
        totals[r["client"]] += hours_between(r["start"], r["end"])
    return {client: round(hours, 2) for client, hours in totals.items()}


class ApiClient:
    """ES: Cliente HTTP mínimo que conserva la cookie de sesión entre llamadas.
       EN: Minimal HTTP client that keeps the session cookie across calls."""

    def __init__(self, base_url: str):
        self.base_url = base_url
        jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

    def _request(self, method: str, path: str, body: dict | None = None) -> dict:
        url = self.base_url + path
        data = None
        headers = {}
        if body is not None:
            data = json.dumps(body).encode("utf-8")
            headers["Content-Type"] = "application/json"
        if method != "GET":
            # ES/EN: exigido por backend/includes/functions.php::require_same_origin_header
            headers["X-Requested-With"] = "ConsultHours"

        req = urllib.request.Request(url, data=data, headers=headers, method=method)
        try:
            with self.opener.open(req, timeout=10) as resp:
                return json.loads(resp.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            body_text = e.read().decode("utf-8", errors="replace")
            raise RuntimeError(f"{method} {path} -> HTTP {e.code}: {body_text}") from e
        except urllib.error.URLError as e:
            raise RuntimeError(
                f"No se pudo conectar a {url} ({e.reason}). "
                "¿Están Apache y MySQL de XAMPP corriendo?"
            ) from e

    def login(self, username: str, password: str) -> None:
        self._request("POST", "/auth/login.php", {"username": username, "password": password})

    def summary(self, month: str) -> dict:
        qs = urllib.parse.urlencode({"month": month})
        return self._request("GET", f"/summary.php?{qs}")


def compare(expected: dict, actual_clients: list, label: str) -> bool:
    actual = {c["client_name"]: float(c["billable_hours"]) for c in actual_clients}
    all_clients = sorted(set(expected) | set(actual))
    ok = True
    for client in all_clients:
        exp = expected.get(client, 0.0)
        act = actual.get(client, 0.0)
        matches = abs(exp - act) < 0.01
        ok &= matches
        status = "OK      " if matches else "MISMATCH"
        print(f"  [{status}] {label:<24} {client:<20} esperado={exp:6.2f}h   api={act:6.2f}h")
    return ok


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.split("EN:")[0])
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL, help="URL base de backend/api")
    args = parser.parse_args()

    seed = load_seed()
    months = sorted({r["date"][:7] for r in seed["time_records"]})
    client = ApiClient(args.base_url)

    print(f"Verificando /api/summary.php contra {SEED_PATH.name}")
    print(f"Backend: {args.base_url}")
    print(f"Meses encontrados en los datos de prueba: {', '.join(months)}\n")

    all_ok = True

    for user in seed["users"]:
        if user["role"] != "consultant":
            continue
        client.login(user["username"], user["password"])
        for month in months:
            expected = expected_summary(seed, month, username=user["username"])
            actual = client.summary(month)
            all_ok &= compare(expected, actual["clients"], f"{user['username']} ({month})")

    admin = next(u for u in seed["users"] if u["role"] == "admin")
    client.login(admin["username"], admin["password"])
    for month in months:
        expected = expected_summary(seed, month, username=None)
        actual = client.summary(month)
        all_ok &= compare(expected, actual["clients"], f"admin/todos ({month})")

    print()
    if all_ok:
        print("Todos los resúmenes de /api/summary.php coinciden con seed_data.json.")
        sys.exit(0)
    else:
        print("Se encontraron discrepancias entre /api/summary.php y seed_data.json.")
        print("Revisa backend/api/summary.php antes de confiar en ese número.")
        sys.exit(1)


if __name__ == "__main__":
    main()
