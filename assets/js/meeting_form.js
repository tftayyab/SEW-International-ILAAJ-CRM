(function () {
  const { $, $all, api, toast, escapeHtml, openModal, closeModal, debounce } = AppUtil;

  const selectedWorkers = new Map();
  const selectedPatients = new Map();

  (window.MEETING_INITIAL?.workers || []).forEach((w) => selectedWorkers.set(Number(w.id), w));
  (window.MEETING_INITIAL?.patients || []).forEach((p) => selectedPatients.set(Number(p.id), p));

  function renderChips() {
    const wBox = $('#selectedWorkers');
    const pBox = $('#selectedPatients');
    const wEmpty = $('#workersEmpty');
    const pEmpty = $('#patientsEmpty');

    const workers = [...selectedWorkers.values()];
    const patients = [...selectedPatients.values()];

    wEmpty.hidden = workers.length > 0;
    pEmpty.hidden = patients.length > 0;

    wBox.innerHTML = workers.map((w) => `
      <span class="chip">
        <span class="chip-label">${escapeHtml(w.name)}</span>
        ${w.phone ? `<span class="chip-meta">${escapeHtml(w.phone)}</span>` : ''}
        <button type="button" data-rm-w="${w.id}" aria-label="Remove">×</button>
      </span>`).join('');
    pBox.innerHTML = patients.map((p) => `
      <span class="chip">
        <span class="chip-label">${escapeHtml(p.name)}</span>
        <span class="chip-meta">${escapeHtml(p.number || '')}${p.city ? ' · ' + escapeHtml(p.city) : ''}</span>
        <button type="button" data-rm-p="${p.id}" aria-label="Remove">×</button>
      </span>`).join('');

    $all('[data-rm-w]', wBox).forEach((btn) => {
      btn.addEventListener('click', () => {
        selectedWorkers.delete(Number(btn.dataset.rmW));
        renderChips();
      });
    });
    $all('[data-rm-p]', pBox).forEach((btn) => {
      btn.addEventListener('click', () => {
        selectedPatients.delete(Number(btn.dataset.rmP));
        renderChips();
      });
    });
  }

  async function openWorkerPicker() {
    const res = await api('workers.php?action=list');
    openModal(`
      <div class="modal-header"><h2>Select expected workers</h2>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button></div>
      <div class="modal-body">
        <div class="picker-toolbar">
          <input type="search" id="workerFilterQ" class="picker-search" placeholder="Filter by name or phone…" autocomplete="off">
          <span class="picker-count muted" id="workerPickCount"></span>
        </div>
        <div class="picker-list" id="workerPickList">
          ${res.workers.map((w) => `
            <label class="picker-row">
              <input type="checkbox" value="${w.id}" data-name="${escapeHtml(w.name)}" data-phone="${escapeHtml(w.phone || '')}" ${selectedWorkers.has(Number(w.id)) ? 'checked' : ''}>
              <span class="picker-main">
                <strong>${escapeHtml(w.name)}</strong>
                <span class="picker-sub">${w.phone ? escapeHtml(w.phone) : 'No phone'}</span>
              </span>
            </label>`).join('') || '<div class="muted">No workers yet. Use + New worker.</div>'}
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="button" class="btn" id="applyWorkers">Done</button>
      </div>
    `, { large: true });

    const list = $('#workerPickList');
    const countEl = $('#workerPickCount');
    function refreshCount() {
      const visible = $all('.picker-row', list).filter((r) => r.style.display !== 'none');
      const checked = $all('input:checked', list).length;
      countEl.textContent = checked + ' selected · ' + visible.length + ' shown';
    }
    refreshCount();

    $('#workerFilterQ').addEventListener('input', debounce((e) => {
      const q = e.target.value.toLowerCase().trim();
      $all('.picker-row', list).forEach((row) => {
        row.style.display = !q || row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
      refreshCount();
    }, 120));
    list.addEventListener('change', refreshCount);

    $('#applyWorkers').addEventListener('click', () => {
      selectedWorkers.clear();
      $all('#workerPickList input:checked').forEach((cb) => {
        selectedWorkers.set(Number(cb.value), {
          id: Number(cb.value),
          name: cb.dataset.name,
          phone: cb.dataset.phone || ''
        });
      });
      closeModal();
      renderChips();
    });
  }

  function openAddWorker() {
    openModal(`
      <div class="modal-header"><h2>Add worker</h2>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button></div>
      <div class="modal-body">
        <form id="newWorkerForm">
          <div class="field"><label>Name *</label><input name="name" required autofocus></div>
          <div class="field" style="margin-top:0.75rem"><label>Phone</label><input name="phone"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="button" class="btn" id="saveNewWorker">Save &amp; select</button>
      </div>`);
    $('#saveNewWorker').addEventListener('click', async () => {
      const data = Object.fromEntries(new FormData($('#newWorkerForm')).entries());
      try {
        const res = await api('workers.php?action=create', { method: 'POST', body: data });
        selectedWorkers.set(res.id, { id: res.id, name: res.worker.name, phone: res.worker.phone || '' });
        closeModal();
        renderChips();
        toast('Worker added.');
      } catch (e) { toast(e.message); }
    });
  }

  function openAddPatient() {
    openModal(`
      <div class="modal-header"><h2>Add patient</h2>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button></div>
      <div class="modal-body">
        <form id="newPatientForm" class="form-grid">
          <div class="field"><label>Patient name *</label><input name="name" required autofocus></div>
          <div class="field"><label>Mother's name</label><input name="mother_name"></div>
          <div class="field"><label>Number *</label><input name="number" required></div>
          <div class="field"><label>Country</label><input name="country"></div>
          <div class="field"><label>City</label><input name="city"></div>
          <div class="field"><label>Occupation</label><input name="occupation"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="button" class="btn" id="saveNewPatient">Save &amp; select</button>
      </div>`);
    $('#saveNewPatient').addEventListener('click', async () => {
      const data = Object.fromEntries(new FormData($('#newPatientForm')).entries());
      try {
        const res = await api('patients.php?action=create', { method: 'POST', body: data });
        const p = res.patient;
        selectedPatients.set(res.id, {
          id: res.id,
          name: p.name,
          number: p.number || '',
          city: p.city || ''
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
      <div class="modal-header"><h2>Select expected patients</h2>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button></div>
      <div class="modal-body">
        <div class="picker-filters" id="patPickFilters">
          <div class="field field-grow"><label>Search</label><input name="q" type="search" placeholder="Name, number, city…" autocomplete="off"></div>
          <div class="field"><label>Number</label><input name="number" autocomplete="off"></div>
          <div class="field"><label>City</label><input name="city" autocomplete="off"></div>
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
            ${retained.has(Number(p.id)) ? 'checked' : ''}>
          <span class="picker-main">
            <strong>${escapeHtml(p.name)}</strong>
            <span class="picker-sub">${escapeHtml(p.number || '—')} · ${escapeHtml(p.city || '—')}</span>
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
              city: cb.dataset.city || ''
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
            city: cb.dataset.city || ''
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
            city: cb.dataset.city || ''
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
    $('#btnPickWorkers').addEventListener('click', () => openWorkerPicker().catch((e) => toast(e.message)));
    $('#btnAddWorkerModal').addEventListener('click', openAddWorker);
    $('#btnPickPatients').addEventListener('click', () => openPatientPicker().catch((e) => toast(e.message)));
    $('#btnAddPatientModal').addEventListener('click', openAddPatient);
    $('#btnSaveMeeting').addEventListener('click', async () => {
      const form = $('#meetingPageForm');
      const data = Object.fromEntries(new FormData(form).entries());
      data.worker_ids = [...selectedWorkers.keys()];
      data.patient_ids = [...selectedPatients.keys()];
      try {
        if (data.id && Number(data.id) > 0) {
          await api('meetings.php?action=update', { method: 'POST', body: data });
          toast('Meeting updated.');
          window.location.href = APP.baseUrl + '/pages/meeting.php?id=' + data.id;
        } else {
          delete data.id;
          const created = await api('meetings.php?action=create', { method: 'POST', body: data });
          toast('Meeting created.');
          window.location.href = APP.baseUrl + '/pages/meeting.php?id=' + created.id;
        }
      } catch (e) { toast(e.message); }
    });
  });
})();
