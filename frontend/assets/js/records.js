/**
 * ES: Lógica de la pestaña "Registros": catálogo de clientes/consultores,
 *     búsqueda con filtros, alta de un nuevo registro y borrado con
 *     control de acceso reflejado también en la UI (además de en el
 *     backend, que es la autoridad real).
 * EN: Logic for the "Records" tab: client/consultant catalogs, filtered
 *     search, creating a new record, and deleting one with access control
 *     mirrored in the UI too (in addition to the backend, which is the
 *     real authority).
 */

let clientsCache = [];
let searchDebounceTimer = null;

async function loadClientsIntoSelects() {
  const { clients } = await api.get('/clients.php');
  clientsCache = clients;

  const options = clients.map((c) => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');

  document.getElementById('search-client').innerHTML = '<option value="">Todos</option>' + options;
  document.getElementById('rf-client').innerHTML = options;
}

async function loadConsultantsIntoSelects() {
  if (state.user.role !== 'admin') return;
  const { consultants } = await api.get('/consultants.php');
  const options = consultants.map((c) => `<option value="${c.id}">${escapeHtml(c.full_name)}</option>`).join('');

  document.getElementById('search-consultant').innerHTML = '<option value="">Todos</option>' + options;
  document.getElementById('summary-consultant').innerHTML = '<option value="">Todos los consultores</option>' + options;
}

function buildRecordsQuery() {
  const params = new URLSearchParams();
  const q = document.getElementById('search-q').value.trim();
  const clientId = document.getElementById('search-client').value;
  const month = document.getElementById('search-month').value;
  const consultantId = document.getElementById('search-consultant').value;

  if (q) params.set('q', q);
  if (clientId) params.set('client_id', clientId);
  if (month) params.set('month', month);
  if (state.user.role === 'admin' && consultantId) params.set('consultant_id', consultantId);

  return params.toString();
}

async function loadRecords() {
  const tbody = document.getElementById('records-tbody');
  const isAdmin = state.user.role === 'admin';
  const colSpan = isAdmin ? 8 : 7;

  try {
    const query = buildRecordsQuery();
    const { records } = await api.get(`/records.php${query ? '?' + query : ''}`);

    if (records.length === 0) {
      tbody.innerHTML = `<tr><td colspan="${colSpan}" class="empty-row">Sin registros para este filtro.</td></tr>`;
      return;
    }

    tbody.innerHTML = records.map((r) => renderRecordRow(r, isAdmin)).join('');

    tbody.querySelectorAll('[data-delete-id]').forEach((btn) => {
      btn.addEventListener('click', () => deleteRecord(btn.dataset.deleteId));
    });
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="${colSpan}" class="empty-row">${escapeHtml(err.message)}</td></tr>`;
  } finally {
    updateRecordsScrollFade();
  }
}

/**
 * ES: Muestra/oculta el degradado ".scroll-fade-right" según si la
 *     tabla de registros realmente tiene más contenido a la derecha del
 *     que se ve, y según qué tan cerca del final ya se hizo scroll.
 *     Encontrado probando el layout a 375px de ancho (ver index.html):
 *     la tabla SÍ se podía desplazar, pero nada en pantalla lo indicaba.
 * EN: Shows/hides the ".scroll-fade-right" gradient depending on whether
 *     the records table actually has more content to the right than is
 *     visible, and how close to the end it has already been scrolled.
 *     Found by testing the layout at a 375px width (see index.html): the
 *     table WAS scrollable, but nothing on screen hinted at it.
 */
function updateRecordsScrollFade() {
  const scrollEl = document.getElementById('records-scroll');
  const fadeEl = document.getElementById('records-scroll-fade');
  if (!scrollEl || !fadeEl) return;

  const hasMoreToShow = scrollEl.scrollWidth - scrollEl.clientWidth - scrollEl.scrollLeft > 4;
  fadeEl.classList.toggle('visible', hasMoreToShow);
}

function renderRecordRow(r, isAdmin) {
  const canManage = state.user.role === 'admin' || state.user.id === r.consultant_id;
  const billablePill = r.billable
    ? '<span class="pill pill-yes">Sí</span>'
    : '<span class="pill pill-no">No</span>';
  const overlapPill = r.overlaps
    ? ' <span class="pill pill-overlap" title="Se traslapa con otro registro tuyo el mismo día">⚠ traslape</span>'
    : '';

  return `
    <tr>
      <td>${formatDate(r.work_date)}</td>
      <td>${escapeHtml(r.client_name)}</td>
      ${isAdmin ? `<td class="admin-only">${escapeHtml(r.consultant_name)}</td>` : ''}
      <td>${r.start_time.slice(0, 5)}–${r.end_time.slice(0, 5)}</td>
      <td>${Number(r.hours).toFixed(2)}h</td>
      <td>${escapeHtml(r.description) || '<span class="empty-row" style="padding:0">—</span>'}${overlapPill}</td>
      <td>${billablePill}</td>
      <td>${canManage ? `<button class="btn btn-danger-ghost" data-delete-id="${r.id}" type="button">Borrar</button>` : ''}</td>
    </tr>`;
}

function formatDate(isoDate) {
  const [y, m, d] = isoDate.split('-');
  return `${d}/${m}/${y}`;
}

async function deleteRecord(id) {
  if (!confirm('¿Borrar este registro de horas? Esta acción no se puede deshacer.')) return;
  try {
    await api.delete(`/records.php?id=${encodeURIComponent(id)}`);
    showToast('Registro borrado.', 'ok');
    loadRecords();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

function initRecordForm() {
  const form = document.getElementById('record-form');
  const newBtn = document.getElementById('new-record-btn');
  const cancelBtn = document.getElementById('record-cancel-btn');
  const errorEl = document.getElementById('record-form-error');

  newBtn.addEventListener('click', () => {
    form.hidden = !form.hidden;
    if (!form.hidden) document.getElementById('rf-date').focus();
  });
  cancelBtn.addEventListener('click', () => {
    form.hidden = true;
    form.reset();
    errorEl.hidden = true;
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorEl.hidden = true;

    const payload = {
      client_id: Number(document.getElementById('rf-client').value),
      work_date: document.getElementById('rf-date').value,
      start_time: document.getElementById('rf-start').value,
      end_time: document.getElementById('rf-end').value,
      description: document.getElementById('rf-description').value.trim(),
      billable: document.getElementById('rf-billable').checked,
    };

    try {
      const result = await api.post('/records.php', payload);
      showToast('Registro guardado.', 'ok');
      if (result.warning) showToast(result.warning, 'warn');
      form.reset();
      form.hidden = true;
      loadRecords();
    } catch (err) {
      errorEl.textContent = err.message;
      errorEl.hidden = false;
    }
  });
}

function initRecordsSearch() {
  const debouncedReload = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(loadRecords, 300);
  };

  document.getElementById('search-q').addEventListener('input', debouncedReload);
  document.getElementById('search-client').addEventListener('change', loadRecords);
  document.getElementById('search-month').addEventListener('change', loadRecords);
  document.getElementById('search-consultant').addEventListener('change', loadRecords);

  document.getElementById('records-scroll').addEventListener('scroll', updateRecordsScrollFade);
  window.addEventListener('resize', updateRecordsScrollFade);
}
