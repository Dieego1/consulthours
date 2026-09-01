#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ES: Suite de pruebas automatizadas del API de ConsultHours.

    Todo lo que se probó a mano con `curl` durante el desarrollo (login,
    autorización por dueño/rol, el intento de suplantación, la detección
    de traslapes, la visibilidad del resumen, el bloqueo por fuerza
    bruta) queda aquí como pruebas repetibles, no como comandos sueltos
    que hay que recordar y volver a escribir cada vez que algo cambia.

    Usa `unittest` de la librería estándar (sin dependencias externas),
    igual que scripts/verify_summary.py.

    Requiere que Apache y MySQL de XAMPP estén corriendo, y que la base
    tenga los datos de seed_data.json (recién sembrados, sin registros
    de prueba de una corrida anterior a medio limpiar).

    Uso:
        python scripts/test_api.py
        python scripts/test_api.py -v      # ES: salida detallada por prueba

EN: Automated test suite for the ConsultHours API.

    Everything that was manually verified with `curl` during development
    (login, owner/role authorization, the impersonation attempt, overlap
    detection, summary visibility, brute-force lockout) lives here as
    repeatable tests, instead of one-off commands that have to be
    remembered and retyped every time something changes.

    Uses the standard library's `unittest` (no external dependencies),
    same as scripts/verify_summary.py.

    Requires XAMPP's Apache and MySQL to be running, and the database to
    have seed_data.json's data (freshly seeded, no half-cleaned test
    records left over from a previous run).

    Usage:
        python scripts/test_api.py
        python scripts/test_api.py -v      # EN: verbose, per-test output
"""

import json
import sys
import unittest
import urllib.error
import urllib.request
import http.cookiejar
import urllib.parse
import uuid
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8")
except AttributeError:
    pass

# ES: Igual que en verify_summary.py: la URL se arma con el nombre REAL
#     de la carpeta del proyecto en disco, no un nombre fijo -- quien
#     clone este repositorio de GitHub va a tener una carpeta llamada
#     "consulthours" (el nombre del repo), no "PRUEBA TECNICA" (el
#     nombre que tenía durante el desarrollo). Así, esta suite corre sin
#     tocar nada sin importar cómo se llame la carpeta.
# EN: Same as verify_summary.py: the URL is built from the project
#     folder's REAL name on disk, not a fixed string -- whoever clones
#     this from GitHub will have a folder named "consulthours" (the
#     repo's name), not "PRUEBA TECNICA" (the name it had during
#     development). This way the suite runs untouched no matter what the
#     folder is named.
PROJECT_ROOT = Path(__file__).resolve().parent.parent
BASE_URL = f"http://localhost/{urllib.parse.quote(PROJECT_ROOT.name)}/backend/api"

USERS = {
    "admin": {"username": "admin", "password": "admin123", "id": 1},
    "carla": {"username": "carla", "password": "carla2024", "id": 2},
    "miguel": {"username": "miguel", "password": "miguel!!", "id": 3},
}


class ApiClient:
    """ES: Cliente HTTP con su propia cookie de sesión, independiente de
       los demás clientes -- así una prueba puede tener a la vez una
       sesión de carla y una de miguel sin que se pisen.
       EN: HTTP client with its own session cookie, independent from
       other clients -- so a test can hold a carla session and a miguel
       session at the same time without them stepping on each other."""

    def __init__(self, base_url: str = BASE_URL):
        self.base_url = base_url
        jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

    def request(self, method: str, path: str, body=None, expect_json: bool = True):
        url = self.base_url + path
        data = None
        headers = {}
        if body is not None:
            data = json.dumps(body).encode("utf-8")
            headers["Content-Type"] = "application/json"
        if method != "GET":
            headers["X-Requested-With"] = "ConsultHours"

        req = urllib.request.Request(url, data=data, headers=headers, method=method)
        try:
            with self.opener.open(req, timeout=10) as resp:
                raw = resp.read().decode("utf-8")
                return resp.status, (json.loads(raw) if expect_json and raw else raw)
        except urllib.error.HTTPError as e:
            raw = e.read().decode("utf-8", errors="replace")
            try:
                return e.code, json.loads(raw)
            except json.JSONDecodeError:
                return e.code, raw
        except urllib.error.URLError as e:
            raise RuntimeError(
                f"No se pudo conectar a {url} ({e.reason}). "
                "¿Están Apache y MySQL de XAMPP corriendo?"
            ) from e

    def login(self, username: str, password: str):
        return self.request("POST", "/auth/login.php", {"username": username, "password": password})

    def get(self, path: str):
        return self.request("GET", path)

    def post(self, path: str, body: dict):
        return self.request("POST", path, body)

    def delete(self, path: str):
        return self.request("DELETE", path)


def login_as(key: str) -> ApiClient:
    client = ApiClient()
    user = USERS[key]
    status, body = client.login(user["username"], user["password"])
    if status != 200:
        raise RuntimeError(f"No se pudo iniciar sesión como {key}: {status} {body}")
    return client


class AuthenticationTests(unittest.TestCase):
    """ES: Autenticación -- ¿existe una sesión? / EN: Authentication -- is there a session?"""

    def test_protected_endpoint_without_session_is_401(self):
        anon = ApiClient()
        status, _ = anon.get("/records.php")
        self.assertEqual(status, 401, "Sin sesión, /records.php debería responder 401")

    def test_login_wrong_password_is_401(self):
        anon = ApiClient()
        status, body = anon.login("carla", "contraseña-incorrecta")
        self.assertEqual(status, 401)
        self.assertNotIn("password_hash", json.dumps(body), "El hash nunca debe salir en la respuesta")

    def test_login_correct_returns_user_without_hash(self):
        anon = ApiClient()
        status, body = anon.login("carla", "carla2024")
        self.assertEqual(status, 200)
        self.assertEqual(body["user"]["username"], "carla")
        self.assertEqual(body["user"]["role"], "consultant")
        self.assertNotIn("password_hash", body["user"])
        self.assertNotIn("password", body["user"])


class OwnershipAndAuthorizationTests(unittest.TestCase):
    """ES: Autorización por dueño/rol -- el corazón del ejercicio.
       EN: Owner/role authorization -- the heart of the exercise."""

    def setUp(self):
        self.carla = login_as("carla")
        self.miguel = login_as("miguel")
        self.admin = login_as("admin")
        self._created_ids = []

    def tearDown(self):
        # ES: limpieza -- borra, como admin, cualquier registro de prueba
        #     que haya quedado vivo.
        # EN: cleanup -- as admin, delete any test record left behind.
        for record_id in self._created_ids:
            self.admin.delete(f"/records.php?id={record_id}")

    def _create_as(self, client: ApiClient, **overrides) -> dict:
        marker = f"TEST-AUTOMATION-{uuid.uuid4().hex[:8]}"
        payload = {
            "client_id": 1,
            "work_date": "2026-07-05",
            "start_time": "09:00",
            "end_time": "10:00",
            "description": marker,
            "billable": True,
        }
        payload.update(overrides)
        status, body = client.post("/records.php", payload)
        self.assertEqual(status, 201, f"No se pudo crear el registro de prueba: {body}")
        self._created_ids.append(body["id"])
        return body

    def test_owner_is_always_the_session_user_never_the_body(self):
        """ES: El bug explícito del ejercicio: crear un registro mandando
               consultant_id de OTRO consultor en el body no debe
               suplantarlo.
           EN: The exercise's explicit bug: creating a record with
               ANOTHER consultant's consultant_id in the body must not
               impersonate them."""
        result = self._create_as(self.carla, consultant_id=USERS["miguel"]["id"])
        status, records = self.carla.get("/records.php")
        self.assertEqual(status, 200)
        created = next(r for r in records["records"] if r["id"] == result["id"])
        self.assertEqual(
            created["consultant_id"], USERS["carla"]["id"],
            "El registro debía quedar a nombre de carla (quien está logueada), no de miguel",
        )

    def test_consultant_cannot_delete_others_record(self):
        result = self._create_as(self.miguel)
        status, _ = self.carla.delete(f"/records.php?id={result['id']}")
        self.assertEqual(status, 403, "carla no debería poder borrar un registro de miguel")

    def test_consultant_can_delete_own_record(self):
        result = self._create_as(self.carla)
        status, _ = self.carla.delete(f"/records.php?id={result['id']}")
        self.assertEqual(status, 200)
        self._created_ids.remove(result["id"])  # ya se borró, no re-borrar en tearDown

    def test_admin_can_delete_any_record(self):
        result = self._create_as(self.carla)
        status, _ = self.admin.delete(f"/records.php?id={result['id']}")
        self.assertEqual(status, 200)
        self._created_ids.remove(result["id"])

    def test_consultant_only_sees_own_records_even_if_filter_says_otherwise(self):
        status, body = self.carla.get(f"/records.php?consultant_id={USERS['miguel']['id']}")
        self.assertEqual(status, 200)
        owners = {r["consultant_id"] for r in body["records"]}
        self.assertEqual(
            owners - {USERS["carla"]["id"]}, set(),
            "carla no debería ver registros de nadie más, sin importar el filtro",
        )

    def test_admin_sees_records_from_all_consultants(self):
        status, body = self.admin.get("/records.php?month=2026-08")
        self.assertEqual(status, 200)
        owners = {r["consultant_id"] for r in body["records"]}
        self.assertIn(USERS["carla"]["id"], owners)
        self.assertIn(USERS["miguel"]["id"], owners)


class SummaryVisibilityTests(unittest.TestCase):
    """ES: Decisión de negocio: visibilidad del resumen (NOTES.md §2.2).
       EN: Business decision: summary visibility (NOTES.md §2.2)."""

    def setUp(self):
        self.carla = login_as("carla")
        self.admin = login_as("admin")

    def test_consultant_cannot_see_another_consultants_summary(self):
        status, body = self.carla.get(f"/summary.php?month=2026-08&consultant_id={USERS['miguel']['id']}")
        self.assertEqual(status, 200)
        self.assertEqual(body["scope"], "single_consultant")
        # ES: si de verdad viera el de miguel, apareceria Initech con horas
        # EN: if it really showed miguel's, Initech would show up with hours
        client_names = {c["client_name"] for c in body["clients"]}
        self.assertNotIn("Initech Solutions", client_names, "Ese cliente es solo de miguel en agosto")

    def test_admin_without_filter_sees_aggregate_of_all_consultants(self):
        status, body = self.admin.get("/summary.php?month=2026-08")
        self.assertEqual(status, 200)
        self.assertEqual(body["scope"], "all_consultants")
        self.assertAlmostEqual(body["total_billable_hours"], 36.5, places=2)


class OverlapDetectionTests(unittest.TestCase):
    """ES: Decisión de negocio: traslapes se marcan, no se bloquean
           (NOTES.md §2.1), usando el ejemplo real del 6 de agosto.
       EN: Business decision: overlaps are flagged, not blocked
           (NOTES.md §2.1), using the real August 6th example."""

    def test_august_6th_carla_records_are_flagged_as_overlapping(self):
        carla = login_as("carla")
        status, body = carla.get("/records.php?month=2026-08")
        self.assertEqual(status, 200)
        aug6 = [r for r in body["records"] if r["work_date"] == "2026-08-06"]
        self.assertEqual(len(aug6), 2, "Deberían existir los dos registros traslapados del seed")
        self.assertTrue(all(r["overlaps"] for r in aug6), "Ambos deben quedar marcados overlaps=true")

    def test_creating_an_overlapping_record_warns_but_does_not_block(self):
        carla = login_as("carla")
        status, body = carla.post("/records.php", {
            "client_id": 1,
            "work_date": "2026-07-02",  # ya existe un registro de carla 09:00-12:00 ese día
            "start_time": "10:00",
            "end_time": "11:00",
            "description": "TEST-AUTOMATION-overlap",
            "billable": True,
        })
        try:
            self.assertEqual(status, 201, "El traslape debe advertir, no bloquear la creación")
            self.assertIsNotNone(body.get("warning"), "Debe venir un mensaje de advertencia")
        finally:
            login_as("admin").delete(f"/records.php?id={body['id']}")


class SqlInjectionTests(unittest.TestCase):
    """ES: Que la búsqueda de texto no sea vulnerable a inyección SQL.
       EN: That the text search isn't vulnerable to SQL injection."""

    def test_search_with_sql_metacharacters_does_not_error_or_leak(self):
        carla = login_as("carla")
        payload = "' OR '1'='1"
        status, body = carla.get(f"/records.php?q={urllib.parse.quote(payload)}")
        self.assertEqual(status, 200, "Un intento de inyección no debería tumbar el endpoint")
        self.assertIsInstance(body["records"], list)


class LoginRateLimitTests(unittest.TestCase):
    """ES: Bloqueo por intentos fallidos, persistido en BD (NOTES.md
           §1.7). Usa un nombre de usuario inventado que nunca existe,
           para no bloquear ninguna cuenta real de la demo.
       EN: Failed-attempt lockout, persisted in the DB (NOTES.md §1.7).
           Uses a made-up username that never exists, so no real demo
           account gets locked out."""

    def test_fifth_failed_attempt_still_401_sixth_is_429(self):
        probe_user = f"test-ratelimit-{uuid.uuid4().hex[:8]}"
        client = ApiClient()
        for i in range(5):
            status, _ = client.login(probe_user, "cualquier-cosa")
            self.assertEqual(status, 401, f"Intento {i + 1} debería ser 401 (usuario/contraseña incorrectos)")

        status, body = client.login(probe_user, "cualquier-cosa")
        self.assertEqual(status, 429, "El 6to intento en la ventana debe quedar bloqueado")
        self.assertIn("Intenta de nuevo", body["error"])


if __name__ == "__main__":
    print(f"Probando contra {BASE_URL}\n")
    unittest.main(verbosity=2)
