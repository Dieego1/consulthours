/**
 * ES: Cliente HTTP mínimo para hablar con el backend PHP. Centraliza:
 *      - la URL base del API,
 *      - el envío de cookies de sesión (credentials: 'same-origin'),
 *      - el encabezado X-Requested-With que el backend exige como
 *        mitigación de CSRF en peticiones que cambian estado
 *        (ver backend/includes/functions.php::require_same_origin_header),
 *      - el manejo uniforme de errores JSON.
 *
 * EN: Minimal HTTP client to talk to the PHP backend. Centralizes:
 *      - the API base URL,
 *      - sending session cookies (credentials: 'same-origin'),
 *      - the X-Requested-With header the backend requires as a CSRF
 *        mitigation on state-changing requests
 *        (see backend/includes/functions.php::require_same_origin_header),
 *      - uniform JSON error handling.
 */

// ES: Ruta relativa: frontend/index.html -> ../backend/api
// EN: Relative path: frontend/index.html -> ../backend/api
const API_BASE = '../backend/api';

class ApiError extends Error {
  constructor(message, status) {
    super(message);
    this.status = status;
  }
}

async function apiRequest(path, { method = 'GET', body = null } = {}) {
  const headers = {};
  let payload = null;

  if (body !== null) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }
  if (method !== 'GET') {
    // ES: ver backend/includes/functions.php::require_same_origin_header
    // EN: see backend/includes/functions.php::require_same_origin_header
    headers['X-Requested-With'] = 'ConsultHours';
  }

  let res;
  try {
    res = await fetch(`${API_BASE}${path}`, {
      method,
      headers,
      body: payload,
      credentials: 'same-origin',
    });
  } catch (networkErr) {
    throw new ApiError('No se pudo conectar con el servidor. ¿Está XAMPP corriendo?', 0);
  }

  let data = {};
  try {
    data = await res.json();
  } catch {
    // ES: respuesta sin cuerpo JSON (poco común, pero no debe romper la app)
    // EN: response without a JSON body (uncommon, but shouldn't crash the app)
  }

  if (!res.ok) {
    throw new ApiError(data.error || `Error ${res.status}`, res.status);
  }

  return data;
}

const api = {
  get:    (path) => apiRequest(path, { method: 'GET' }),
  post:   (path, body) => apiRequest(path, { method: 'POST', body }),
  delete: (path) => apiRequest(path, { method: 'DELETE' }),
};
