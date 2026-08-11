(function () {
  const { $, $all, api, toast, escapeHtml, formatDate, truncate, debounce, ImageCache } = AppUtil;

  let page = 1;
  let currentPatientId = null;
  let lastForcedId = null;
  let lastForcedAt = null;
  let userNavigatedAway = false;

  async function loadList() {
    ImageCache.clear();
    const params = new URLSearchParams({
      action: 'list',
      page,
      per_page: 12,
      sort: 'last_activity',
      dir: 'DESC'
    });
    const q = $('#advisorSearch').value.trim();
    const country = $('#advisorCountry').value.trim();
    const city = $('#advisorCity').value.trim();
    if (q) params.set('q', q);
    if (country) params.set('country', country);
    if (city) params.set('city', city);

    const res = await api('patients.php?' + params.toString());
    const box = $('#advisorCards');
    if (!res.data.length) {
      box.innerHTML = '<div class="empty-state">No patients found.</div>';
    } else {
      box.innerHTML = res.data.map((p) => `
        <button type="button" class="patient-card" data-id="${p.id}">
          ${p.profile_image_id ? `<img class="avatar img-loading" data-image-id="${p.profile_image_id}" alt="">` : ''}
          <h3>${escapeHtml(p.name)}</h3>
          <div class="meta">
            <div>Mother: ${escapeHtml(p.mother_name || '—')}</div>
            <div>${escapeHtml(p.number)}</div>
            <div>${escapeHtml(p.city || '—')}${p.country ? ', ' + escapeHtml(p.country) : ''}</div>
            <div>${escapeHtml(p.occupation || '—')}</div>
            <div>Last: ${escapeHtml(formatDate(p.last_activity) || '—')}</div>
          </div>
        </button>
      `).join('');
      ImageCache.loadAll(box);
      $all('[data-id]', box).forEach((btn) => {
        btn.addEventListener('click', () => {
          userNavigatedAway = true;
          openPatient(parseInt(btn.dataset.id, 10), false);
        });
      });
    }

    const pg = $('#advisorPagination');
    const pgn = res.pagination;
    pg.innerHTML = `
      <button class="btn btn-secondary btn-sm" ${pgn.page <= 1 ? 'disabled' : ''} data-page="${pgn.page - 1}">Prev</button>
      <span>Page ${pgn.page} / ${pgn.total_pages}</span>
      <button class="btn btn-secondary btn-sm" ${pgn.page >= pgn.total_pages ? 'disabled' : ''} data-page="${pgn.page + 1}">Next</button>`;
    $all('[data-page]', pg).forEach((b) => b.addEventListener('click', () => {
      page = parseInt(b.dataset.page, 10);
      loadList().catch((e) => toast(e.message));
    }));
  }

  async function openPatient(id, fromForce) {
    currentPatientId = id;
    if (fromForce) userNavigatedAway = false;
    ImageCache.clear();

    const [pRes, mRes] = await Promise.all([
      api('patients.php?action=get&id=' + id),
      api('messages.php?action=list&patient_id=' + id)
    ]);
    const p = pRes.patient;

    $('#advisorListView').hidden = true;
    $('#advisorDetailView').hidden = false;
    $('#advName').textContent = p.name;

    const avatar = p.profile_image_id
      ? `<img class="avatar-lg img-loading" data-image-id="${p.profile_image_id}" alt="">`
      : '';

    $('#advHero').innerHTML = `
      ${avatar}
      <div style="flex:1;min-width:220px">
        <div class="info-grid">
          <div><strong>Mother</strong><span>${escapeHtml(p.mother_name || '—')}</span></div>
          <div><strong>Number</strong><span>${escapeHtml(p.number)}</span></div>
          <div><strong>Country</strong><span>${escapeHtml(p.country || '—')}</span></div>
          <div><strong>City</strong><span>${escapeHtml(p.city || '—')}</span></div>
          <div><strong>Occupation</strong><span>${escapeHtml(p.occupation || '—')}</span></div>
        </div>
        ${p.notes ? `<div class="notes-box"><strong>Notes</strong><div style="margin-top:0.35rem;white-space:pre-wrap">${escapeHtml(p.notes)}</div></div>` : ''}
        ${Number(p.image_count) > 0 ? `<p class="profile-only-note" style="margin-top:0.75rem">Profile picture shown above. <a href="${APP.baseUrl}/pages/gallery.php?id=${p.id}">Open gallery</a> for all photos.</p>` : ''}
      </div>`;
    ImageCache.loadAll($('#advHero'));

    const gal = $('#btnAdvGallery');
    if (Number(p.image_count) > 0) {
      gal.hidden = false;
      gal.href = APP.baseUrl + '/pages/gallery.php?id=' + id;
    } else {
      gal.hidden = true;
      gal.removeAttribute('href');
    }

    const conv = $('#advConversation');
    if (!mRes.messages.length) {
      conv.innerHTML = '<div class="empty-state">This patient has no conversations yet.</div>';
    } else {
      conv.innerHTML = mRes.messages.map((m) => `
        <div class="message-block ${escapeHtml(m.sender_type)}">
          <div class="message-date">${m.message_date ? escapeHtml(formatDate(m.message_date)) : '<span class="muted">—</span>'}</div>
          <div class="message-sender">${m.sender_type === 'ameer_sahab' ? 'Ameer Sahab' : 'Patient'}</div>
          <div class="message-text">${escapeHtml(m.message_text)}</div>
        </div>`).join('');
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function showList() {
    userNavigatedAway = true;
    currentPatientId = null;
    if (window.history.replaceState) {
      window.history.replaceState({}, '', APP.baseUrl + '/pages/advisor.php');
    }
    $('#advisorDetailView').hidden = true;
    $('#advisorListView').hidden = false;
    loadList().catch((e) => toast(e.message));
  }

  async function pollActive() {
    try {
      const res = await api('active_patient.php?action=get');
      const state = res.state;
      const forcedId = state.active_patient_id;
      const updatedAt = state.updated_at;

      if (forcedId && (forcedId !== lastForcedId || updatedAt !== lastForcedAt)) {
        const isNewForce = lastForcedAt !== null && updatedAt !== lastForcedAt;
        const isInitial = lastForcedAt === null;
        lastForcedId = forcedId;
        lastForcedAt = updatedAt;

        if (isInitial || isNewForce || forcedId !== currentPatientId) {
          if (forcedId !== currentPatientId) {
            const banner = $('#forcedBanner');
            banner.classList.add('show');
            setTimeout(() => banner.classList.remove('show'), 2500);
            await openPatient(forcedId, true);
          }
        }
      } else if (forcedId) {
        lastForcedId = forcedId;
        lastForcedAt = updatedAt;
      }
    } catch (e) {
      // silent poll failures
    }
  }

  const live = debounce(() => {
    page = 1;
    loadList().catch((e) => toast(e.message));
  }, 280);

  document.addEventListener('DOMContentLoaded', () => {
    ['advisorSearch', 'advisorCountry', 'advisorCity'].forEach((id) => {
      const el = $('#' + id);
      if (el) el.addEventListener('input', live);
    });
    $('#btnBackToList').addEventListener('click', showList);
    const qPatient = parseInt(new URLSearchParams(window.location.search).get('patient') || '0', 10);
    if (qPatient > 0) {
      openPatient(qPatient, false).catch((e) => toast(e.message));
    } else {
      loadList().catch((e) => toast(e.message));
    }
    pollActive();
    setInterval(pollActive, 2500);
  });
})();
