/**
 * ES: Lógica de la pestaña "Resumen mensual": llama a
 *     /backend/api/summary.php y dibuja la tabla + el gráfico de barras.
 *     La autorización real (quién puede ver el resumen de quién) vive en
 *     el backend; aquí solo se muestra u oculta el selector de consultor
 *     para admins.
 * EN: Logic for the "Monthly summary" tab: calls
 *     /backend/api/summary.php and renders the table + bar chart. The
 *     real authorization (who can see whose summary) lives in the
 *     backend; here we only show/hide the consultant selector for admins.
 */

async function loadSummary() {
  const month = document.getElementById('summary-month').value || currentYearMonth();
  document.getElementById('summary-month').value = month;

  const params = new URLSearchParams({ month });
  if (state.user.role === 'admin') {
    const consultantId = document.getElementById('summary-consultant').value;
    if (consultantId) params.set('consultant_id', consultantId);
  }

  const tbody = document.getElementById('summary-tbody');
  const chart = document.getElementById('summary-chart');
  const hint = document.getElementById('summary-scope-hint');

  try {
    const data = await api.get(`/summary.php?${params.toString()}`);

    hint.textContent = data.scope === 'all_consultants'
      ? 'Mostrando el total agregado de todos los consultores.'
      : 'Mostrando únicamente tus propias horas facturables.';

    if (data.clients.length === 0) {
      tbody.innerHTML = '<tr><td colspan="3" class="empty-row">Sin horas facturables este mes.</td></tr>';
      chart.innerHTML = '';
    } else {
      const maxHours = Math.max(...data.clients.map((c) => c.billable_hours));

      tbody.innerHTML = data.clients.map((c) => `
        <tr>
          <td>${escapeHtml(c.client_name)}</td>
          <td>${Number(c.billable_hours).toFixed(2)}h</td>
          <td>${c.record_count}</td>
        </tr>`).join('');

      chart.innerHTML = data.clients.map((c) => `
        <div class="chart-row">
          <span>${escapeHtml(c.client_name)}</span>
          <span class="chart-track"><span class="chart-fill" data-width="${(c.billable_hours / maxHours) * 100}"></span></span>
          <span>${Number(c.billable_hours).toFixed(2)}h</span>
        </div>`).join('');

      // ES: se asigna el ancho en un segundo paso para que la transición
      //     CSS "width" se dispare al pasar de 0% al valor real.
      // EN: width is assigned in a second pass so the CSS "width"
      //     transition triggers when going from 0% to the real value.
      requestAnimationFrame(() => {
        chart.querySelectorAll('.chart-fill').forEach((el) => {
          el.style.width = `${el.dataset.width}%`;
        });
      });
    }

    document.getElementById('summary-total').textContent = `${Number(data.total_billable_hours).toFixed(2)}h`;
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="3" class="empty-row">${escapeHtml(err.message)}</td></tr>`;
    chart.innerHTML = '';
  }
}

function currentYearMonth() {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

function initSummaryUi() {
  document.getElementById('summary-month').value = currentYearMonth();
  document.getElementById('summary-month').addEventListener('change', loadSummary);
  document.getElementById('summary-consultant').addEventListener('change', loadSummary);
}
