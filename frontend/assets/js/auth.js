/**
 * ES: Estado global de sesión + utilidades compartidas (toasts, escape de
 *     HTML) + lógica de login/logout. Los demás módulos (records.js,
 *     summary.js) leen `state.user` para saber el rol actual.
 * EN: Global session state + shared utilities (toasts, HTML escaping) +
 *     login/logout logic. Other modules (records.js, summary.js) read
 *     `state.user` to know the current role.
 */

const state = {
  user: null,
};

/**
 * ES: Escapa texto antes de insertarlo como HTML, para evitar XSS con
 *     datos que vienen del backend (descripciones, nombres, etc.) aunque
 *     ya se validen del lado del servidor — defensa en profundidad.
 * EN: Escapes text before inserting it as HTML, to avoid XSS with data
 *     coming from the backend (descriptions, names, etc.) even though it
 *     is already validated server-side — defense in depth.
 */
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function showToast(message, type = 'ok') {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.add('leaving');
    setTimeout(() => toast.remove(), 260);
  }, 3800);
}

function applyRoleToDom() {
  document.body.dataset.role = state.user?.role || '';
}

async function checkExistingSession() {
  try {
    const { user } = await api.get('/auth/session.php');
    state.user = user;
    return true;
  } catch {
    state.user = null;
    return false;
  }
}

function renderUserBadge() {
  if (!state.user) return;
  document.getElementById('user-name').textContent = state.user.full_name;
  const roleBadge = document.getElementById('user-role');
  roleBadge.textContent = state.user.role === 'admin' ? 'Administrador' : 'Consultor';
  roleBadge.classList.toggle('role-admin', state.user.role === 'admin');
}

function initAuthUi({ onLoginSuccess, onLogout }) {
  const loginForm = document.getElementById('login-form');
  const loginError = document.getElementById('login-error');
  const loginSubmit = document.getElementById('login-submit');

  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    loginError.hidden = true;
    loginSubmit.disabled = true;

    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;

    try {
      const { user } = await api.post('/auth/login.php', { username, password });
      state.user = user;
      applyRoleToDom();
      renderUserBadge();
      loginForm.reset();
      onLoginSuccess();
    } catch (err) {
      loginError.textContent = err.message;
      loginError.hidden = false;
    } finally {
      loginSubmit.disabled = false;
    }
  });

  document.getElementById('logout-btn').addEventListener('click', async () => {
    try {
      await api.post('/auth/logout.php', {});
    } catch {
      // ES: aunque falle la llamada, igual limpiamos el estado local
      // EN: even if the call fails, still clear local state
    }
    state.user = null;
    onLogout();
  });
}
