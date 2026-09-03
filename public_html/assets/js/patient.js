(function () {
  const {
    $, $all, api, toast, escapeHtml, formatDate, openModal, closeModal, confirmDeletePhrase,
    ImageCache, bindAvatarPicker, uploadPatientAvatar, readPatientNavContext,
    fetchPatientNeighbors, patientUrlWithPage, listReturnUrl, openWhatsApp
  } = AppUtil;

  let patientId = parseInt($('#patientHero').dataset.patientId, 10);
  let navCtx = readPatientNavContext();
  let messagesCache = [];
  let activeCompose = null;
  let responseSent = $('#patientHero').dataset.responseSent !== '0';

  function hasAmeerResponse() {
    return messagesCache.some((m) => m.sender_type === 'ameer_sahab');
  }

  function syncResponseSentButton() {
    let btn = $('#btnToggleResponseSent');
    if (!hasAmeerResponse()) {
      if (btn) btn.remove();
      return;
    }
    if (!btn) {
      btn = document.createElement('button');
      btn.type = 'button';
      btn.id = 'btnToggleResponseSent';
      btn.addEventListener('click', () => {
        toggleResponseSent().catch((e) => toast(e.message));
      });
      const anchor = $('#btnSendToAmeer');
      anchor.parentNode.insertBefore(btn, anchor);
    }
    if (responseSent) {
      btn.textContent = 'Unsend response';
      btn.className = 'btn btn-secondary';
    } else {
      btn.textContent = 'Send response';
      btn.className = 'btn';
    }
  }

  function lastAmeerMessageText() {
    const msg = messagesCache.find((m) => m.sender_type === 'ameer_sahab');
    return (msg && msg.message_text) ? String(msg.message_text) : '';
  }

  function patientNumber() {
    return $('#patientHero').dataset.patientNumber || '';
  }

  async function toggleResponseSent() {
    const btn = $('#btnToggleResponseSent');
    if (!btn) return;
    const nextSent = !responseSent;
    btn.disabled = true;
    const prevLabel = btn.textContent;
    btn.textContent = nextSent ? 'Sending…' : 'Unsending…';
    try {
      const res = await api('patients.php?action=set_response_sent', {
        method: 'POST',
        body: { id: patientId, sent: nextSent },
      });
      responseSent = !!Number(res.patient?.response_sent);
      $('#patientHero').dataset.responseSent = responseSent ? '1' : '0';
      if (res.patient && res.patient.number) {
        $('#patientHero').dataset.patientNumber = res.patient.number;
      }
      toast(responseSent ? 'Response marked as sent.' : 'Response unsent.');
      syncResponseSentButton();
      if (nextSent) {
        const text = lastAmeerMessageText();
        if (!text) {
          toast('No Ameer Sahab message to send on WhatsApp.');
        } else {
          openWhatsApp(patientNumber(), text);
        }
      }
    } catch (e) {
      toast(e.message);
      btn.textContent = prevLabel;
    } finally {
      btn.disabled = false;
    }
  }

  function todayIso() {
    return new Date().toISOString().slice(0, 10);
  }

  function senderLabel(type) {
    return type === 'ameer_sahab' ? 'Ameer Sahab' : 'Patient';
  }

  function nextSenderType(msgs) {
    if (!msgs.length) return 'patient';
    return msgs[0].sender_type === 'patient' ? 'ameer_sahab' : 'patient';
  }

  function composeBlockHtml(msg) {
    const sender = msg.sender_type;
    return `
      <div class="message-block ${escapeHtml(sender)} message-block--compose" data-compose-id="${msg.id || 'new'}">
        <div class="message-date">${escapeHtml(formatDate(msg.message_date || todayIso()))}</div>
        <div class="message-sender">${escapeHtml(senderLabel(sender))}</div>
        <textarea class="message-compose-input" rows="4" required placeholder="Write the message…">${escapeHtml(msg.message_text || '')}</textarea>
        <div class="actions message-compose-actions">
          <button type="button" class="btn btn-sm btn-secondary" data-cancel-compose>Cancel</button>
          <button type="button" class="btn btn-sm" data-save-compose>Save message</button>
        </div>
      </div>`;
  }

  function viewBlockHtml(m) {
    return `
      <div class="message-block ${escapeHtml(m.sender_type)}" data-msg-id="${m.id}">
        <div class="message-date">${m.message_date ? escapeHtml(formatDate(m.message_date)) : '<span class="muted">—</span>'}</div>
        <div class="message-sender">${escapeHtml(senderLabel(m.sender_type))}</div>
        <div class="message-text">${escapeHtml(m.message_text)}</div>
        <div class="actions" style="margin-top:0.65rem">
          <button type="button" class="btn btn-sm btn-secondary" data-edit-msg="${m.id}">Edit</button>
          <button type="button" class="btn btn-sm btn-danger" data-del-msg="${m.id}">Delete</button>
        </div>
      </div>`;
  }

  function renderMessages() {
    const box = $('#conversation');
    const parts = [];

    if (activeCompose === 'new') {
      parts.push(composeBlockHtml({
        sender_type: nextSenderType(messagesCache),
        message_date: todayIso(),
        message_text: '',
      }));
    }

    messagesCache.forEach((m) => {
      if (activeCompose === m.id) {
        parts.push(composeBlockHtml({
          id: m.id,
          sender_type: m.sender_type,
          message_date: todayIso(),
          message_text: m.message_text,
        }));
      } else {
        parts.push(viewBlockHtml(m));
      }
    });

    if (!parts.length) {
      box.innerHTML = '<div class="empty-state">This patient has no conversations yet.</div>';
    } else {
      box.innerHTML = parts.join('');
    }

    const composeInput = $('.message-compose-input', box);
    if (composeInput) composeInput.focus();

    $all('[data-edit-msg]', box).forEach((btn) => {
      btn.addEventListener('click', () => {
        const msg = messagesCache.find((x) => String(x.id) === btn.dataset.editMsg);
        if (msg) openEditMessage(msg);
      });
    });
    $all('[data-del-msg]', box).forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('Delete this message?')) return;
        try {
          await api('messages.php?action=delete', { method: 'POST', body: { id: btn.dataset.delMsg } });
          toast('Message deleted.');
          activeCompose = null;
          await loadMessages();
          await refreshResponseSentState();
        } catch (e) { toast(e.message); }
      });
    });
    $all('[data-cancel-compose]', box).forEach((btn) => {
      btn.addEventListener('click', () => {
        activeCompose = null;
        renderMessages();
      });
    });
    $all('[data-save-compose]', box).forEach((btn) => {
      btn.addEventListener('click', () => saveCompose(btn.closest('[data-compose-id]')));
    });
    syncResponseSentButton();
  }

  async function saveCompose(block) {
    if (!block) return;
    const textarea = $('.message-compose-input', block);
    const text = textarea.value.trim();
    if (!text) {
      textarea.focus();
      toast('Please enter a message.');
      return;
    }
    const composeId = block.dataset.composeId;
    const isNew = composeId === 'new';
    const existing = isNew ? null : messagesCache.find((m) => String(m.id) === composeId);
    const sender = isNew ? nextSenderType(messagesCache) : existing.sender_type;
    const data = {
      patient_id: patientId,
      sender_type: sender,
      message_date: todayIso(),
      message_text: text,
    };
    if (!isNew) data.id = existing.id;

    const saveBtn = $('[data-save-compose]', block);
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving…';
    try {
      if (isNew) {
        await api('messages.php?action=create', { method: 'POST', body: data });
      } else {
        await api('messages.php?action=update', { method: 'POST', body: data });
      }
      activeCompose = null;
      toast('Message saved.');
      if (sender === 'ameer_sahab') {
        responseSent = false;
        $('#patientHero').dataset.responseSent = '0';
      }
      await loadMessages();
    } catch (e) {
      toast(e.message);
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save message';
    }
  }

  function openAddMessage() {
    if (activeCompose === 'new') {
      const ta = $('.message-compose-input', $('#conversation'));
      if (ta) ta.focus();
      return;
    }
    if (activeCompose) return;
    activeCompose = 'new';
    renderMessages();
  }

  function openEditMessage(msg) {
    activeCompose = msg.id;
    renderMessages();
  }

  async function loadMessages() {
    const res = await api('messages.php?action=list&patient_id=' + patientId);
    messagesCache = res.messages || [];
    renderMessages();
  }

  async function refreshResponseSentState() {
    try {
      const res = await api('patients.php?action=get&id=' + patientId);
      responseSent = !!Number(res.patient?.response_sent);
      $('#patientHero').dataset.responseSent = responseSent ? '1' : '0';
      syncResponseSentButton();
    } catch (e) {
      // ignore
    }
  }

  async function refreshPatientHeader() {
    const res = await api('patients.php?action=get&id=' + patientId);
    const p = res.patient;
    $('#patientHero').dataset.patientNumber = p.number || '';
    $('#patientInfo').innerHTML = `
      <div><strong>Patient</strong><span id="patientName">${escapeHtml(p.name)}</span></div>
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
        <div class="modal-header">
          <div>
            <h2>Edit patient</h2>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button>
        </div>
        <div class="modal-body">
          <form id="editPatientForm" class="form-grid">
            <div class="field full">
              <div class="modal-section-label">Profile photo</div>
              <div class="avatar-picker">
                <div class="avatar-picker__preview" id="avatarPreview">${p.profile_image_id ? `<img data-image-id="${p.profile_image_id}" class="img-loading" alt="">` : 'No<br>photo'}</div>
                <div class="avatar-picker__meta">
                  <strong>Change profile picture</strong>
                  <p>JPG, PNG, GIF or WebP. Leave empty to keep the current photo.</p>
                  <div class="avatar-picker__actions">
                    <button type="button" class="btn btn-sm btn-secondary" id="avatarChoose">Choose photo</button>
                    <button type="button" class="btn btn-sm btn-ghost" id="avatarClear" hidden>Remove</button>
                  </div>
                </div>
                <input type="file" id="avatarFile" accept="image/jpeg,image/png,image/gif,image/webp">
              </div>
            </div>
            <div class="field full"><div class="modal-section-label">Details</div></div>
            <div class="field"><label>Patient name *</label><input type="text" name="name" required value="${escapeHtml(p.name)}" placeholder="Full name"></div>
            <div class="field"><label>Mother's name</label><input type="text" name="mother_name" value="${escapeHtml(p.mother_name || '')}" placeholder="Optional"></div>
            <div class="field"><label>Number *</label><input type="tel" name="number" required inputmode="tel" value="${escapeHtml(p.number)}" placeholder="Phone number"></div>
            <div class="field"><label>Occupation</label><input type="text" name="occupation" value="${escapeHtml(p.occupation || '')}" placeholder="Optional"></div>
            <div class="field"><label>City</label><input type="text" name="city" value="${escapeHtml(p.city || '')}" placeholder="Optional"></div>
            <div class="field"><label>Country</label><input type="text" name="country" value="${escapeHtml(p.country || '')}" placeholder="Optional"></div>
            <div class="field full"><label>Notes</label><textarea name="notes" placeholder="Private notes for the editor…">${escapeHtml(p.notes || '')}</textarea></div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
          <button type="button" class="btn" id="savePatient">Save changes</button>
        </div>`);

      const avatar = bindAvatarPicker($('#modalRoot'));
      const existingImg = $('#avatarPreview img');
      if (existingImg && p.profile_image_id) ImageCache.load(p.profile_image_id, existingImg);

      const saveBtn = $('#savePatient');
      saveBtn.addEventListener('click', async () => {
        const form = $('#editPatientForm');
        if (!form.reportValidity()) return;
        const data = Object.fromEntries(new FormData(form).entries());
        data.id = patientId;
        const file = avatar.getFile();
        try {
          saveBtn.disabled = true;
          saveBtn.textContent = 'Saving…';
          await api('patients.php?action=update', { method: 'POST', body: data });
          if (file) {
            saveBtn.textContent = 'Uploading photo…';
            await uploadPatientAvatar(patientId, file);
          }
          closeModal();
          toast(file ? 'Patient and photo updated.' : 'Patient updated.');
          await refreshPatientHeader();
        } catch (e) {
          toast(e.message);
          saveBtn.disabled = false;
          saveBtn.textContent = 'Save changes';
        }
      });
    }).catch((e) => toast(e.message));
  }

  async function setupRecordNav() {
    const back = $('#patientBackLink');
    if (back) {
      back.href = listReturnUrl(navCtx);
    }
    try {
      const nav = await fetchPatientNeighbors(patientId, navCtx);
      const prevBtn = $('#btnPrevPatient');
      const nextBtn = $('#btnNextPatient');
      if (prevBtn) {
        prevBtn.disabled = !nav.prev_id;
        prevBtn.onclick = nav.prev_id
          ? () => { window.location.href = patientUrlWithPage(nav.prev_id, navCtx, nav.prev_page); }
          : null;
      }
      if (nextBtn) {
        nextBtn.disabled = !nav.next_id;
        nextBtn.onclick = nav.next_id
          ? () => { window.location.href = patientUrlWithPage(nav.next_id, navCtx, nav.next_page); }
          : null;
      }
    } catch (e) {
      // keep back link even if neighbors fail
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    $('#btnAddMessage').addEventListener('click', openAddMessage);
    $('#btnEditPatient').addEventListener('click', openEditPatient);
    $('#btnSendToAmeer').addEventListener('click', async () => {
      try {
        await api('patients.php?action=set_active', { method: 'POST', body: { id: patientId } });
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
        window.location.href = listReturnUrl(navCtx);
      } catch (e) { toast(e.message); }
    });
    ImageCache.loadAll($('#patientHero'));
    setupRecordNav().catch(() => {});
    loadMessages().then(() => refreshResponseSentState()).catch((e) => toast(e.message));
  });
})();
