/**
 * ES: Arranque de la aplicación: decide si mostrar el login o el shell
 *     principal, conecta las pestañas y activa el efecto de inclinación
 *     3D de las tarjetas al mover el mouse.
 * EN: Application bootstrap: decides whether to show the login screen or
 *     the main shell, wires up the tabs, and enables the mouse-driven 3D
 *     tilt effect on cards.
 */

function showApp() {
  document.getElementById('view-login').hidden = true;
  document.getElementById('view-shell').hidden = false;
  applyRoleToDom();
  renderUserBadge();
  bootstrapAppData();
}

function showLogin() {
  document.getElementById('view-login').hidden = false;
  document.getElementById('view-shell').hidden = true;
}

async function bootstrapAppData() {
  await Promise.all([loadClientsIntoSelects(), loadConsultantsIntoSelects()]);
  loadRecords();
  loadSummary();
}

function initTabs() {
  const buttons = [...document.querySelectorAll('.tab-btn')];
  const indicator = document.getElementById('tab-indicator');

  function positionIndicator(btn) {
    indicator.style.width = `${btn.offsetWidth}px`;
    indicator.style.transform = `translateX(${btn.offsetLeft - 4}px)`;
  }

  function activate(tabName) {
    buttons.forEach((b) => b.classList.toggle('active', b.dataset.tab === tabName));
    document.querySelectorAll('.tab-panel').forEach((p) => {
      p.classList.toggle('active', p.id === `tab-${tabName}`);
    });
    positionIndicator(buttons.find((b) => b.dataset.tab === tabName));

    // ES: Refresca los datos del panel que se muestra. Antes, cambiar de
    //     pestaña no volvía a pedir nada: si creabas un registro en
    //     "Registros" y luego entrabas a "Resumen mensual" sin tocar el
    //     selector de mes (que ya decía el mes correcto, así que no
    //     disparaba su evento "change"), veías los números de la última
    //     vez que se cargó esa pestaña, no los actuales. El guard
    //     `if (state.user)` evita que esto dispare una petición al API
    //     antes de haber iniciado sesión (esta función también se llama
    //     una vez al cargar la página, antes del login).
    // EN: Refreshes the panel being shown. Before, switching tabs never
    //     re-fetched anything: if you created a record in "Registros"
    //     and then opened "Resumen mensual" without touching the month
    //     picker (which already showed the right month, so its "change"
    //     event never fired), you'd see numbers from the last time that
    //     tab loaded, not the current ones. The `if (state.user)` guard
    //     stops this from firing a request before login (this function
    //     also runs once on page load, before anyone has logged in).
    if (state.user) {
      if (tabName === 'records') loadRecords();
      if (tabName === 'summary') loadSummary();
    }
  }

  buttons.forEach((btn) => btn.addEventListener('click', () => activate(btn.dataset.tab)));
  window.addEventListener('resize', () => {
    const active = buttons.find((b) => b.classList.contains('active'));
    if (active) positionIndicator(active);
  });

  // ES: posición inicial una vez que el layout ya calculó anchos reales
  // EN: initial position once layout has computed real widths
  requestAnimationFrame(() => activate('records'));
}

/**
 * ES: Inclinación 3D suave al mover el mouse sobre una tarjeta. Se limita
 *     el ángulo y se usa solo `transform` para que no cause "layout" ni
 *     "paint" costosos (fluido incluso con varias tarjetas en pantalla).
 * EN: Smooth 3D tilt as the mouse moves over a card. The angle is capped
 *     and only `transform` is touched, so it never triggers expensive
 *     layout/paint (stays fluid even with several cards on screen).
 */
function initCardTilt() {
  const MAX_DEG = 4;
  document.querySelectorAll('.card-3d').forEach((card) => {
    card.addEventListener('mouseenter', () => card.classList.add('tilt-active'));
    card.addEventListener('mousemove', (e) => {
      const rect = card.getBoundingClientRect();
      const px = (e.clientX - rect.left) / rect.width;   // 0..1
      const py = (e.clientY - rect.top) / rect.height;   // 0..1
      card.style.setProperty('--rx', `${(px - 0.5) * MAX_DEG * 2}deg`);
      card.style.setProperty('--ry', `${(0.5 - py) * MAX_DEG * 2}deg`);
    });
    card.addEventListener('mouseleave', () => {
      card.classList.remove('tilt-active');
      card.style.setProperty('--rx', '0deg');
      card.style.setProperty('--ry', '0deg');
    });
  });
}

/**
 * ES: Los usuarios de prueba del login son clicables: rellenan el
 *     formulario con su usuario/contraseña (tomados de data-username /
 *     data-password) para no tener que copiarlos a mano. No envían el
 *     formulario solos -- el clic en "Iniciar sesión" lo sigue dando la
 *     persona, a propósito, para no ocultar el flujo normal de login.
 * EN: The login screen's test users are clickable: they fill the form
 *     with their username/password (read from data-username /
 *     data-password) so nobody has to copy them by hand. They don't
 *     submit the form on their own -- clicking "Iniciar sesión" is still
 *     up to the person, on purpose, so the normal login flow stays visible.
 */
function initTestUserAutofill() {
  function fillFrom(item) {
    document.getElementById('login-username').value = item.dataset.username;
    document.getElementById('login-password').value = item.dataset.password;
    document.getElementById('login-username').focus();
  }

  document.querySelectorAll('.test-user').forEach((item) => {
    item.addEventListener('click', () => fillFrom(item));
    // ES: accesible por teclado (role="button" + tabindex, ver index.html)
    // EN: keyboard-accessible (role="button" + tabindex, see index.html)
    item.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        fillFrom(item);
      }
    });
  });
}

async function init() {
  initTabs();
  initCardTilt();
  initRecordForm();
  initRecordsSearch();
  initSummaryUi();
  initTestUserAutofill();

  initAuthUi({
    onLoginSuccess: showApp,
    onLogout: showLogin,
  });

  const hasSession = await checkExistingSession();
  if (hasSession) {
    showApp();
  } else {
    showLogin();
  }
}

document.addEventListener('DOMContentLoaded', init);
