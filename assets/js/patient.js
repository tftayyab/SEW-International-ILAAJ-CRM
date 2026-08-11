(function () {
  const { $, $all, api, toast, escapeHtml, formatDate, openModal, closeModal, confirmDeletePhrase, ImageCache } = AppUtil;
  const patientId = parseInt($('#patientHero').dataset.patientId, 10);

  async function loadMessages() {
    const res = await api('messages.php?action=list&patient_id=' + patientId);
    const box = $('#conversation');
    if (!res.messages.length) {
      box.innerHTML = '<div class="empty-state">This patient has no conversations yet.</div>';
      return;
    }
    box.innerHTML = res.messages.map((m) => `
      <div class="message-block ${escapeHtml(m.sender_type)}">
          <div class="message-date">${m.message_date ? escapeHtml(formatDate(m.message_date)) : '<span class="muted">—</span>'}</div>
        <div class="message-sender">${m.sender_type === 'ameer_sahab' ? 'Ameer Sahab' : 'Patient'}</div>
        <div class="message-text">${escapeHtml(m.message_text)}</div>
        <div class="actions" style="margin-top:0.65rem">
          <button type="button" class="btn btn-sm btn-secondary" data-edit-msg="${m.id}">Edit</button>
          <button type="button" class="btn btn-sm btn-danger" data-del-msg="${m.id}">Delete</button>
        </div>
      </div>
    `).join('');

    $all('[data-edit-msg]', box).forEach((btn) => {
      const msg = res.messages.find((x) => String(x.id) === btn.dataset.editMsg);
      btn.addEventListener('click', () => openMessageForm(msg));
    });
    $all('[data-del-msg]', box).forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('Delete this message?')) return;
        try {
          await api('messages.php?action=delete', { method: 'POST', body: { id: btn.dataset.delMsg } });
          toast('Message deleted.');
          await loadMessages();
        } catch (e) { toast(e.message); }
      });
    });
  }

  function openMessageForm(msg) {
    msg = msg || { sender_type: 'patient', message_date: new Date().toISOString().slice(0, 10) };
    openModal(`
      <div class="modal-header"><h2>${msg.id ? 'Edit message' : 'Add message'}</h2>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button></div>
      <div class="modal-body">
        <form id="msgForm" class="form-grid">
          <input type="hidden" name="id" value="${msg.id || ''}">
          <div class="field"><label>Sender</label>
            <select name="sender_type">
              <option value="patient" ${msg.sender_type === 'patient' ? 'selected' : ''}>Patient</option>
              <option value="ameer_sahab" ${msg.sender_type === 'ameer_sahab' ? 'selected' : ''}>Ameer Sahab</option>
            </select>
          </div>
          <div class="field"><label>Date</label><input type="date" name="message_date" value="${escapeHtml(msg.message_date || '')}"></div>
          <div class="field full"><label>Message</label><textarea name="message_text" required>${escapeHtml(msg.message_text || '')}</textarea></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="button" class="btn" id="saveMsg">Save</button>
      </div>
    `);
    $('#saveMsg').addEventListener('click', async () => {
      const data = Object.fromEntries(new FormData($('#msgForm')).entries());
      data.patient_id = patientId;
      try {
        if (data.id) await api('messages.php?action=update', { method: 'POST', body: data });
        else await api('messages.php?action=create', { method: 'POST', body: data });
        closeModal();
        toast('Message saved.');
        await loadMessages();
      } catch (e) { toast(e.message); }
    });
  }

  async function refreshPatientHeader() {
    const res = await api('patients.php?action=get&id=' + patientId);
    const p = res.patient;
    $('#patientName').textContent = p.name;
    $('#patientInfo').innerHTML = `
      <div><strong>Mother</strong><span>${escapeHtml(p.mother_name || '—')}</span></div>
      <div><strong>Number</strong><span>${escapeHtml(p.number)}</span></div>
      <div><strong>Country</strong><span>${escapeHtml(p.country || '—')}</span></div>
      <div><strong>City</strong><span>${escapeHtml(p.city || '—')}</span></div>
      <div><strong>Occupation</strong><span>${escapeHtml(p.occupation || '—')}</span></div>`;
    const notes = $('#patientNotes');
    if (p.notes) {
      notes.hidden = false;
      notes.innerHTML = `<strong>Notes</strong><div style="margin-top:0.35rem;white-space:pre-wrap">${escapeHtml(p.notes)}</div>`;
    } else {
      notes.hidden = true;
    }
    const hero = $('#patientHero');
    let avatar = hero.querySelector('.avatar-lg');
    if (p.profile_image_id) {
      if (!avatar) {
        avatar = document.createElement('img');
        avatar.className = 'avatar-lg img-loading';
        avatar.alt = '';
        hero.prepend(avatar);
      }
      avatar.setAttribute('data-image-id', String(p.profile_image_id));
      ImageCache.load(p.profile_image_id, avatar);
    } else if (avatar) {
      avatar.remove();
    }
  }

  function openEditPatient() {
    api('patients.php?action=get&id=' + patientId).then((res) => {
      const p = res.patient;
      openModal(`
        <div class="modal-header"><h2>Edit patient</h2>
          <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button></div>
        <div class="modal-body">
          <form id="editPatientForm" class="form-grid">
            <div class="field"><label>Patient name *</label><input name="name" required value="${escapeHtml(p.name)}"></div>
            <div class="field"><label>Mother's name</label><input name="mother_name" value="${escapeHtml(p.mother_name || '')}"></div>
            <div class="field"><label>Number *</label><input name="number" required value="${escapeHtml(p.number)}"></div>
            <div class="field"><label>Country</label><input name="country" value="${escapeHtml(p.country || '')}"></div>
            <div class="field"><label>City</label><input name="city" value="${escapeHtml(p.city || '')}"></div>
            <div class="field"><label>Occupation</label><input name="occupation" value="${escapeHtml(p.occupation || '')}"></div>
            <div class="field full"><label>Notes</label><textarea name="notes">${escapeHtml(p.notes || '')}</textarea></div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
          <button type="button" class="btn" id="savePatient">Save</button>
        </div>`);
      $('#savePatient').addEventListener('click', async () => {
        const data = Object.fromEntries(new FormData($('#editPatientForm')).entries());
        data.id = patientId;
        try {
          await api('patients.php?action=update', { method: 'POST', body: data });
          closeModal();
          toast('Patient updated.');
          await refreshPatientHeader();
        } catch (e) { toast(e.message); }
      });
    }).catch((e) => toast(e.message));
  }

  document.addEventListener('DOMContentLoaded', () => {
    $('#btnAddMessage').addEventListener('click', () => openMessageForm(null));
    $('#btnEditPatient').addEventListener('click', openEditPatient);
    $('#btnSendToAmeer').addEventListener('click', async () => {
      try {
        await api('patients.php?action=set_active', { method: 'POST', body: { id: patientId } });
        const banner = $('#ameerSyncBanner');
        if (banner) {
          banner.classList.add('show');
          banner.innerHTML = 'Presented again — Ameer Sahab should now see <strong>' + escapeHtml($('#patientName').textContent) + '</strong>.';
        }
        toast('Presented to Ameer Sahab.');
      } catch (e) {
        toast(e.message);
      }
    });
    $('#btnDeletePatient').addEventListener('click', async () => {
      const ok = await confirmDeletePhrase({
        title: 'Delete patient',
        phrase: 'DELETE THIS PATIENT',
        warning: 'Deleting this patient will permanently delete everything related to this patient.\n\nThis includes:\n- Patient information\n- Conversations\n- Images\n- Meeting attendance/history associated with the patient\n- Other related patient records'
      });
      if (!ok) return;
      try {
        await api('patients.php?action=delete', { method: 'POST', body: { id: patientId, confirm_phrase: 'DELETE THIS PATIENT' } });
        toast('Patient deleted.');
        window.location.href = APP.baseUrl + '/pages/patients.php';
      } catch (e) { toast(e.message); }
    });
    ImageCache.loadAll($('#patientHero'));
    loadMessages().catch((e) => toast(e.message));
  });
})();
