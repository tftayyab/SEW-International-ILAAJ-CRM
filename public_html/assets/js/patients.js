(function () {
  const {
    $, $all, api, toast, escapeHtml, formatDate, truncate, openModal, closeModal,
    confirmDeletePhrase, debounce, icons, ImageCache, bindAvatarPicker, uploadPatientAvatar,
    withView, LIST_PER_PAGE, readListContext, syncListUrl, patientDetailUrl
  } = AppUtil;

  let ctx = readListContext('patients');
  let state = { sort: ctx.sort, dir: ctx.dir };

  function filters() {
    const root = $('#patientFilters');
    const data = {};
    $all('input', root).forEach((el) => {
      if (el.name && el.value.trim()) data[el.name] = el.value.trim();
    });
    return data;
  }

  async function load() {
    ctx.sort = state.sort;
    ctx.dir = state.dir;
    syncListUrl('/pages/patients.php', ctx);
    if (ctx.q) {
      const search = $('#patientFilters input[name="q"]');
      if (search) search.value = ctx.q;
    }

    const f = filters();
    const params = new URLSearchParams({
      action: 'list',
      page: ctx.page,
      sort: state.sort,
      dir: state.dir,
      per_page: LIST_PER_PAGE,
      ...f
    });
    const res = await api('patients.php?' + params.toString());
    render(res.data, res.pagination);
  }

  const liveLoad = debounce(() => {
    ctx.page = 1;
    ctx.q = ($('#patientFilters input[name="q"]') || {}).value?.trim() || '';
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
              <tr class="row-link" data-href="${patientDetailUrl(p.id, ctx)}">
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
        ${rows.map((p, i) => {
          const tones = ['tone-peach', 'tone-mint', 'tone-sky', 'tone-yellow'];
          const dateText = p.last_activity ? escapeHtml(formatDate(p.last_activity)) : 'No activity';
          return `
          <div class="info-card mobile-card ${tones[i % tones.length]} row-link" data-href="${patientDetailUrl(p.id, ctx)}">
            <div class="info-card__top">
              <span class="info-card__status is-info">Patient</span>
              <span class="info-card__date">${dateText}</span>
            </div>
            <h3 class="info-card__name">${escapeHtml(p.name)}</h3>
            ${p.mother_name ? `<p class="info-card__sub">Mother: ${escapeHtml(p.mother_name)}</p>` : `<p class="info-card__sub">${escapeHtml(p.number)}${p.city ? ' · ' + escapeHtml(p.city) : ''}</p>`}
            <p class="info-card__msg">${escapeHtml(truncate(p.last_message || 'No messages yet.', 220))}</p>
            <div class="info-card__actions" onclick="event.stopPropagation()">${actionBtns(p)}</div>
          </div>`;
        }).join('')}
      </div>`;

    $all('.row-link', wrap).forEach((row) => {
      row.addEventListener('click', () => { window.location.href = withView(row.dataset.href); });
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
        ctx.page = parseInt(b.dataset.page, 10);
        load().catch((e) => toast(e.message));
      });
    });
  }

  function patientFormHtml(p) {
    p = p || {};
    const isEdit = !!p.id;
    return `
      <div class="modal-header">
        <div>
          <h2>${isEdit ? 'Edit patient' : 'Add patient'}</h2>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <form id="patientForm" class="form-grid">
          <input type="hidden" name="id" value="${p.id || ''}">

          <div class="field full">
            <div class="modal-section-label">Profile photo</div>
            <div class="avatar-picker">
              <div class="avatar-picker__preview" id="avatarPreview">${isEdit && p.profile_image_id ? `<img data-image-id="${p.profile_image_id}" class="img-loading" alt="">` : 'No<br>photo'}</div>
              <div class="avatar-picker__meta">
                <strong>${isEdit ? 'Change profile picture' : 'Add profile picture'}</strong>
                <p>JPG, PNG, GIF or WebP. Optional — you can also add photos later from the gallery.</p>
                <div class="avatar-picker__actions">
                  <button type="button" class="btn btn-sm btn-secondary" id="avatarChoose">Choose photo</button>
                  <button type="button" class="btn btn-sm btn-ghost" id="avatarClear" hidden>Remove</button>
                </div>
              </div>
              <input type="file" id="avatarFile" accept="image/jpeg,image/png,image/gif,image/webp">
            </div>
          </div>

          <div class="field full"><div class="modal-section-label">Details</div></div>
          <div class="field"><label>Patient name *</label><input type="text" name="name" required autocomplete="name" value="${escapeHtml(p.name || '')}" placeholder="Full name"></div>
          <div class="field"><label>Mother's name</label><input type="text" name="mother_name" value="${escapeHtml(p.mother_name || '')}" placeholder="Optional"></div>
          <div class="field"><label>Number *</label><input type="tel" name="number" required inputmode="tel" value="${escapeHtml(p.number || '')}" placeholder="Phone number"></div>
          <div class="field"><label>Occupation</label><input type="text" name="occupation" value="${escapeHtml(p.occupation || '')}" placeholder="Optional"></div>
          <div class="field"><label>City</label><input type="text" name="city" value="${escapeHtml(p.city || '')}" placeholder="Optional"></div>
          <div class="field"><label>Country</label><input type="text" name="country" value="${escapeHtml(p.country || '')}" placeholder="Optional"></div>
          <div class="field full"><label>Notes</label><textarea name="notes" placeholder="Private notes for the editor…">${escapeHtml(p.notes || '')}</textarea></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="button" class="btn" id="savePatientBtn">${isEdit ? 'Save changes' : 'Create patient'}</button>
      </div>`;
  }

  async function openPatientForm(id) {
    let patient = null;
    if (id) {
      const res = await api('patients.php?action=get&id=' + id);
      patient = res.patient;
    }
    openModal(patientFormHtml(patient));
    const avatar = bindAvatarPicker($('#modalRoot'));
    const existingImg = $('#avatarPreview img');
    if (existingImg && patient && patient.profile_image_id) {
      ImageCache.load(patient.profile_image_id, existingImg);
    }

    const saveBtn = $('#savePatientBtn');
    saveBtn.addEventListener('click', async () => {
      const form = $('#patientForm');
      if (!form.reportValidity()) return;
      const data = Object.fromEntries(new FormData(form).entries());
      const file = avatar.getFile();
      try {
        saveBtn.disabled = true;
        saveBtn.textContent = data.id ? 'Saving…' : 'Creating…';

        if (data.id) {
          await api('patients.php?action=update', { method: 'POST', body: data });
          if (file) {
            saveBtn.textContent = 'Uploading photo…';
            await uploadPatientAvatar(data.id, file);
          }
          toast(file ? 'Patient and photo updated.' : 'Patient updated.');
          closeModal();
          await load();
        } else {
          const created = await api('patients.php?action=create', { method: 'POST', body: data });
          if (file) {
            saveBtn.textContent = 'Uploading photo…';
            try {
              await uploadPatientAvatar(created.id, file);
            } catch (upErr) {
              toast('Patient created, but photo upload failed: ' + upErr.message);
              window.location.href = patientDetailUrl(created.id, ctx);
              return;
            }
          }
          toast(file ? 'Patient created with profile photo.' : 'Patient created.');
          closeModal();
          window.location.href = patientDetailUrl(created.id, ctx);
        }
      } catch (e) {
        toast(e.message);
        saveBtn.disabled = false;
        saveBtn.textContent = data.id ? 'Save changes' : 'Create patient';
      }
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
      ctx.page = 1;
      ctx.q = '';
      load().catch((e) => toast(e.message));
    });
    load().catch((e) => toast(e.message));
  });
})();
