(function () {
  const { $, $all, api, toast, escapeHtml, formatDate, truncate, openModal, closeModal, confirmDeletePhrase, debounce, icons } = AppUtil;

  let state = { page: 1, sort: 'last_activity', dir: 'DESC' };

  function filters() {
    const root = $('#patientFilters');
    const data = {};
    $all('input', root).forEach((el) => {
      if (el.name && el.value.trim()) data[el.name] = el.value.trim();
    });
    return data;
  }

  async function load() {
    const f = filters();
    const params = new URLSearchParams({
      action: 'list',
      page: state.page,
      sort: state.sort,
      dir: state.dir,
      per_page: 20,
      ...f
    });
    const res = await api('patients.php?' + params.toString());
    render(res.data, res.pagination);
  }

  const liveLoad = debounce(() => {
    state.page = 1;
    load().catch((e) => toast(e.message));
  }, 280);

  function actionBtns(p) {
    return `
      <div class="icon-actions" onclick="event.stopPropagation()">
        <button type="button" class="icon-btn icon-ameer" data-ameer="${p.id}" title="Present to Ameer Sahab">${icons.present}</button>
        <button type="button" class="icon-btn" data-edit="${p.id}" title="Edit">${icons.edit}</button>
        <button type="button" class="icon-btn icon-danger" data-del="${p.id}" title="Delete">${icons.trash}</button>
      </div>`;
  }

  function render(rows, pagination) {
    const wrap = $('#patientsTable');
    if (!rows.length) {
      wrap.innerHTML = '<div class="empty-state">No patients found.</div>';
      $('#patientsPagination').innerHTML = '';
      return;
    }

    wrap.innerHTML = `
      <div class="table-wrap table-desktop">
        <table class="data-table">
          <thead><tr>
            <th data-sort="name">Patient</th>
            <th data-sort="number">Number</th>
            <th data-sort="country">Country</th>
            <th data-sort="city">City</th>
            <th data-sort="occupation">Occupation</th>
            <th data-sort="last_activity">Last activity</th>
            <th>Last message</th>
            <th></th>
          </tr></thead>
          <tbody>
            ${rows.map((p) => `
              <tr class="row-link" data-href="${APP.baseUrl}/pages/patient.php?id=${p.id}">
                <td><div class="cell-primary">${escapeHtml(p.name)}</div><div class="cell-sub">${escapeHtml(p.mother_name || '')}</div></td>
                <td>${escapeHtml(p.number)}</td>
                <td>${escapeHtml(p.country || '—')}</td>
                <td>${escapeHtml(p.city || '—')}</td>
                <td>${escapeHtml(p.occupation || '—')}</td>
                <td>${escapeHtml(formatDate(p.last_activity) || '—')}</td>
                <td class="truncate" title="${escapeHtml(p.last_message || '')}">${escapeHtml(truncate(p.last_message || '—', 60))}</td>
                <td>${actionBtns(p)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
      <div class="mobile-list">
        ${rows.map((p) => `
          <div class="mobile-card row-link" data-href="${APP.baseUrl}/pages/patient.php?id=${p.id}">
            <h3>${escapeHtml(p.name)}</h3>
            <div class="mobile-meta">
              <div>${escapeHtml(p.number)} · ${escapeHtml(p.city || '—')}</div>
              <div>${escapeHtml(truncate(p.last_message || 'No messages', 80))}</div>
            </div>
            <div style="margin-top:0.75rem">${actionBtns(p)}</div>
          </div>
        `).join('')}
      </div>`;

    $all('.row-link', wrap).forEach((row) => {
      row.addEventListener('click', () => { window.location.href = row.dataset.href; });
    });
    $all('[data-sort]', wrap).forEach((th) => {
      th.addEventListener('click', () => {
        const s = th.getAttribute('data-sort');
        if (state.sort === s) state.dir = state.dir === 'ASC' ? 'DESC' : 'ASC';
        else { state.sort = s; state.dir = 'ASC'; }
        load().catch((e) => toast(e.message));
      });
    });
    $all('[data-ameer]', wrap).forEach((btn) => {
      btn.addEventListener('click', async () => {
        try {
          await api('patients.php?action=set_active', { method: 'POST', body: { id: btn.dataset.ameer } });
          toast('Presented to Ameer Sahab.');
        } catch (e) { toast(e.message); }
      });
    });
    $all('[data-edit]', wrap).forEach((btn) => {
      btn.addEventListener('click', () => openPatientForm(parseInt(btn.dataset.edit, 10)));
    });
    $all('[data-del]', wrap).forEach((btn) => {
      btn.addEventListener('click', () => deletePatient(parseInt(btn.dataset.del, 10)));
    });

    const pg = $('#patientsPagination');
    pg.innerHTML = `
      <button class="btn btn-secondary btn-sm" ${pagination.page <= 1 ? 'disabled' : ''} data-page="${pagination.page - 1}">Prev</button>
      <span>Page ${pagination.page} / ${pagination.total_pages} (${pagination.total})</span>
      <button class="btn btn-secondary btn-sm" ${pagination.page >= pagination.total_pages ? 'disabled' : ''} data-page="${pagination.page + 1}">Next</button>`;
    $all('[data-page]', pg).forEach((b) => {
      b.addEventListener('click', () => {
        state.page = parseInt(b.dataset.page, 10);
        load().catch((e) => toast(e.message));
      });
    });
  }

  function patientFormHtml(p) {
    p = p || {};
    return `
      <div class="modal-header">
        <h2>${p.id ? 'Edit patient' : 'Add patient'}</h2>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <form id="patientForm" class="form-grid">
          <input type="hidden" name="id" value="${p.id || ''}">
          <div class="field"><label>Patient name *</label><input name="name" required value="${escapeHtml(p.name || '')}"></div>
          <div class="field"><label>Mother's name</label><input name="mother_name" value="${escapeHtml(p.mother_name || '')}"></div>
          <div class="field"><label>Number *</label><input name="number" required value="${escapeHtml(p.number || '')}"></div>
          <div class="field"><label>Country</label><input name="country" value="${escapeHtml(p.country || '')}"></div>
          <div class="field"><label>City</label><input name="city" value="${escapeHtml(p.city || '')}"></div>
          <div class="field"><label>Occupation</label><input name="occupation" value="${escapeHtml(p.occupation || '')}"></div>
          <div class="field full"><label>Notes</label><textarea name="notes">${escapeHtml(p.notes || '')}</textarea></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="button" class="btn" id="savePatientBtn">Save</button>
      </div>`;
  }

  async function openPatientForm(id) {
    let patient = null;
    if (id) {
      const res = await api('patients.php?action=get&id=' + id);
      patient = res.patient;
    }
    openModal(patientFormHtml(patient));
    $('#savePatientBtn').addEventListener('click', async () => {
      const data = Object.fromEntries(new FormData($('#patientForm')).entries());
      try {
        if (data.id) {
          await api('patients.php?action=update', { method: 'POST', body: data });
          toast('Patient updated.');
          closeModal();
          await load();
        } else {
          const created = await api('patients.php?action=create', { method: 'POST', body: data });
          toast('Patient created.');
          closeModal();
          window.location.href = APP.baseUrl + '/pages/patient.php?id=' + created.id;
        }
      } catch (e) { toast(e.message); }
    });
  }

  async function deletePatient(id) {
    const ok = await confirmDeletePhrase({
      title: 'Delete patient',
      phrase: 'DELETE THIS PATIENT',
      warning: 'Deleting this patient will permanently delete everything related to this patient.\n\nThis includes:\n- Patient information\n- Conversations\n- Images\n- Meeting attendance/history associated with the patient\n- Other related patient records'
    });
    if (!ok) return;
    try {
      await api('patients.php?action=delete', { method: 'POST', body: { id, confirm_phrase: 'DELETE THIS PATIENT' } });
      toast('Patient deleted.');
      await load();
    } catch (e) { toast(e.message); }
  }

  document.addEventListener('DOMContentLoaded', () => {
    $('#btnAddPatient').addEventListener('click', () => openPatientForm(null).catch((e) => toast(e.message)));
    $all('#patientFilters input').forEach((input) => {
      input.addEventListener('input', liveLoad);
    });
    $('#btnReset').addEventListener('click', () => {
      $all('input', $('#patientFilters')).forEach((i) => { i.value = ''; });
      state.page = 1;
      load().catch((e) => toast(e.message));
    });
    load().catch((e) => toast(e.message));
  });
})();
