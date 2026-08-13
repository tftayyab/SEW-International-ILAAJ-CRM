(function () {
  const { $, $all, api, toast, escapeHtml, openModal, closeModal, debounce, withView } = AppUtil;

  const selectedPatients = new Map();

  (window.MEETING_INITIAL?.patients || []).forEach((p) => selectedPatients.set(Number(p.id), p));

  function renderChips() {
    const pBox = $('#selectedPatients');
    const pEmpty = $('#patientsEmpty');

    const patients = [...selectedPatients.values()];
    pEmpty.hidden = patients.length > 0;

    pBox.innerHTML = patients.map((p) => `
      <span class="chip">
        <span class="chip-label">${escapeHtml(p.name)}</span>
        <span class="chip-meta">${escapeHtml(p.number || '')}${p.city ? ' · ' + escapeHtml(p.city) : ''}${p.country ? ' · ' + escapeHtml(p.country) : ''}</span>
        <button type="button" data-rm-p="${p.id}" aria-label="Remove">×</button>
      </span>`).join('');

    $all('[data-rm-p]', pBox).forEach((btn) => {
      btn.addEventListener('click', () => {
        selectedPatients.delete(Number(btn.dataset.rmP));
        renderChips();
      });
    });
  }

  function openAddPatient() {
    openModal(`
      <div class="modal-header">
        <div>
          <h2>Add patient</h2>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <form id="newPatientForm" class="form-grid">
          <div class="field"><label>Patient name *</label><input type="text" name="name" required autofocus placeholder="Full name"></div>
          <div class="field"><label>Mother's name</label><input type="text" name="mother_name" placeholder="Optional"></div>
          <div class="field"><label>Number *</label><input type="tel" name="number" required inputmode="tel" placeholder="Phone number"></div>
          <div class="field"><label>Occupation</label><input type="text" name="occupation" placeholder="Optional"></div>
          <div class="field"><label>City</label><input type="text" name="city" placeholder="Optional"></div>
          <div class="field"><label>Country</label><input type="text" name="country" placeholder="Optional"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="button" class="btn" id="saveNewPatient">Save &amp; select</button>
      </div>`);
    $('#saveNewPatient').addEventListener('click', async () => {
      const form = $('#newPatientForm');
      if (!form.reportValidity()) return;
      const data = Object.fromEntries(new FormData(form).entries());
      try {
        const res = await api('patients.php?action=create', { method: 'POST', body: data });
        const p = res.patient;
        selectedPatients.set(res.id, {
          id: res.id,
          name: p.name,
          number: p.number || '',
          city: p.city || '',
          country: p.country || ''
        });
        closeModal();
        renderChips();
        toast('Patient added.');
      } catch (e) { toast(e.message); }
    });
  }

  async function openPatientPicker() {
    let retained = new Map(selectedPatients);

    async function loadPatients(filters) {
      const params = new URLSearchParams({ action: 'list', page: 1, per_page: 80, sort: 'name', dir: 'ASC' });
      Object.entries(filters || {}).forEach(([k, v]) => { if (v) params.set(k, v); });
      return api('patients.php?' + params.toString());
    }

    const first = await loadPatients({});
    openModal(`
      <div class="modal-header">
        <div>
          <h2>Select expected patients</h2>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <div class="picker-filters" id="patPickFilters">
          <div class="field field-grow"><label>Search</label><input name="q" type="search" placeholder="Name, mother, number, city, or country…" autocomplete="off"></div>
        </div>
        <div class="picker-toolbar">
          <label class="checkbox-item picker-select-all"><input type="checkbox" id="patSelectAll"> Select all shown</label>
          <span class="picker-count muted" id="patPickCount"></span>
        </div>
        <div class="picker-list" id="patientPickList"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="button" class="btn" id="applyPatients">Done</button>
      </div>
    `, { large: true });

    function paint(rows) {
      const box = $('#patientPickList');
      box.innerHTML = rows.map((p) => `
        <label class="picker-row">
          <input type="checkbox" value="${p.id}"
            data-name="${escapeHtml(p.name)}"
            data-number="${escapeHtml(p.number || '')}"
            data-city="${escapeHtml(p.city || '')}"
            data-country="${escapeHtml(p.country || '')}"
            ${retained.has(Number(p.id)) ? 'checked' : ''}>
          <span class="picker-main">
            <strong>${escapeHtml(p.name)}</strong>
            <span class="picker-sub">${escapeHtml(p.number || '—')} · ${escapeHtml(p.city || '—')} · ${escapeHtml(p.country || '—')}</span>
          </span>
        </label>`).join('') || '<div class="muted">No patients match.</div>';

      $all('input[type="checkbox"]', box).forEach((cb) => {
        cb.addEventListener('change', () => {
          const id = Number(cb.value);
          if (cb.checked) {
            retained.set(id, {
              id,
              name: cb.dataset.name,
              number: cb.dataset.number || '',
              city: cb.dataset.city || '',
              country: cb.dataset.country || ''
            });
          } else {
            retained.delete(id);
          }
          refreshCount(rows.length);
        });
      });
      $('#patSelectAll').checked = false;
      refreshCount(rows.length);
    }

    function refreshCount(shown) {
      $('#patPickCount').textContent = retained.size + ' selected · ' + (shown != null ? shown : $all('.picker-row', $('#patientPickList')).length) + ' shown';
    }

    paint(first.data);

    const runFilter = debounce(async () => {
      $all('#patientPickList input[type="checkbox"]').forEach((cb) => {
        const id = Number(cb.value);
        if (cb.checked) {
          retained.set(id, {
            id,
            name: cb.dataset.name,
            number: cb.dataset.number || '',
            city: cb.dataset.city || '',
            country: cb.dataset.country || ''
          });
        }
      });
      const f = {};
      $all('#patPickFilters input').forEach((el) => {
        if (el.name && el.value.trim()) f[el.name] = el.value.trim();
      });
      try {
        const res = await loadPatients(f);
        paint(res.data);
      } catch (e) { toast(e.message); }
    }, 280);

    $all('#patPickFilters input').forEach((el) => el.addEventListener('input', runFilter));

    $('#patSelectAll').addEventListener('change', (e) => {
      $all('#patientPickList input[type="checkbox"]').forEach((cb) => {
        cb.checked = e.target.checked;
        const id = Number(cb.value);
        if (e.target.checked) {
          retained.set(id, {
            id,
            name: cb.dataset.name,
            number: cb.dataset.number || '',
            city: cb.dataset.city || '',
            country: cb.dataset.country || ''
          });
        } else {
          retained.delete(id);
        }
      });
      refreshCount();
    });

    $('#applyPatients').addEventListener('click', () => {
      selectedPatients.clear();
      retained.forEach((v, k) => selectedPatients.set(k, v));
      closeModal();
      renderChips();
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    renderChips();
    $('#btnPickPatients').addEventListener('click', () => openPatientPicker().catch((e) => toast(e.message)));
    $('#btnAddPatientModal').addEventListener('click', openAddPatient);
    $('#btnSaveMeeting').addEventListener('click', async () => {
      const form = $('#meetingPageForm');
      const data = Object.fromEntries(new FormData(form).entries());
      data.patient_ids = [...selectedPatients.keys()];
      try {
        if (data.id && Number(data.id) > 0) {
          await api('meetings.php?action=update', { method: 'POST', body: data });
          toast('Meeting updated.');
          window.location.href = withView(APP.baseUrl + '/pages/meeting.php?id=' + data.id);
        } else {
          delete data.id;
          const created = await api('meetings.php?action=create', { method: 'POST', body: data });
          toast('Meeting created.');
          window.location.href = withView(APP.baseUrl + '/pages/meeting.php?id=' + created.id);
        }
      } catch (e) { toast(e.message); }
    });
  });
})();
