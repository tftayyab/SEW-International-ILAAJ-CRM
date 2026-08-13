(function () {
  const { $, $all, api, toast, escapeHtml, formatDate, debounce, ImageCache } = AppUtil;

  let page = 1;
  let currentPatientId = null;
  let lastForcedNonce = null;

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
    if (q) params.set('q', q);

    const res = await api('patients.php?' + params.toString());
    const box = $('#advisorCards');
    if (!res.data.length) {
      box.innerHTML = '<div class="empty-state">No patients found.</div>';
    } else {
      // Same card design as Pending replies — no images on list cards
      const tones = ['tone-peach', 'tone-mint', 'tone-sky', 'tone-yellow'];
      box.innerHTML = res.data.map((p, i) => {
        const dateText = p.last_activity ? escapeHtml(formatDate(p.last_activity)) : 'No activity';
        return `
        <button type="button" class="info-card ${tones[i % tones.length]}" data-id="${p.id}">
          <div class="info-card__top">
            <span class="info-card__status is-info">Patient</span>
            <span class="info-card__date">${dateText}</span>
          </div>
          <div class="info-card__identity">
            <h3 class="info-card__name">${escapeHtml(p.name)}</h3>
            ${p.mother_name ? `<p class="info-card__sub"><span>Mother</span> ${escapeHtml(p.mother_name)}</p>` : ''}
          </div>
          <span class="info-card__cta">Open conversation →</span>
        </button>`;
      }).join('');
      $all('[data-id]', box).forEach((btn) => {
        btn.addEventListener('click', () => {
          openPatient(parseInt(btn.dataset.id, 10)).catch((e) => toast(e.message));
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

  async function openPatient(id) {
    currentPatientId = id;
    ImageCache.clear();

    const [pRes, mRes] = await Promise.all([
      api('patients.php?action=get&id=' + id),
      api('messages.php?action=list&patient_id=' + id)
    ]);
    const p = pRes.patient;

    $('#advisorListView').hidden = true;
    $('#advisorDetailView').hidden = false;
    const heading = document.querySelector('.app-topbar h1');
    if (heading) heading.hidden = true;

    const avatar = p.profile_image_id
      ? `<img class="avatar-lg img-loading" data-image-id="${p.profile_image_id}" alt="">`
      : '';

    $('#advHero').innerHTML = `
      ${avatar || '<div class="avatar-lg avatar-lg--empty" aria-hidden="true"></div>'}
      <div class="advisor-identity">
        <h3 class="advisor-identity__name">${escapeHtml(p.name)}</h3>
        <div class="advisor-identity__mother">
          <span class="advisor-identity__label">Mother</span>
          <span class="advisor-identity__value">${escapeHtml(p.mother_name || '—')}</span>
        </div>
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

    if (window.history.replaceState) {
      window.history.replaceState({}, '', APP.baseUrl + '/pages/advisor.php?patient=' + id);
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function showList() {
    currentPatientId = null;
    if (window.history.replaceState) {
      window.history.replaceState({}, '', APP.baseUrl + '/pages/advisor.php');
    }
    $('#advisorDetailView').hidden = true;
    $('#advisorListView').hidden = false;
    const heading = document.querySelector('.app-topbar h1');
    if (heading) heading.hidden = false;
    loadList().catch((e) => toast(e.message));
  }

  /**
   * Only open a patient when the Editor freshly presents one.
   * On first load we only remember the current forced state — stay on the list.
   */
  async function pollActive() {
    try {
      const res = await api('active_patient.php?action=get');
      const state = res.state;
      const forcedId = state.active_patient_id;
      const nonce = Number(state.present_nonce || 0);

      if (lastForcedNonce === null) {
        lastForcedNonce = nonce;
        return;
      }

      const isNewForce = nonce !== lastForcedNonce;
      lastForcedNonce = nonce;

      if (isNewForce && forcedId && forcedId !== currentPatientId) {
        const banner = $('#forcedBanner');
        banner.classList.add('show');
        setTimeout(() => banner.classList.remove('show'), 2500);
        await openPatient(forcedId);
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

    // Always start on the list. Only open a patient if URL has ?patient= (e.g. from gallery back).
    const qPatient = parseInt(new URLSearchParams(window.location.search).get('patient') || '0', 10);
    if (qPatient > 0) {
      openPatient(qPatient).catch((e) => toast(e.message));
    } else {
      loadList().catch((e) => toast(e.message));
    }

    if (new URLSearchParams(window.location.search).get('presented')) {
      const banner = $('#forcedBanner');
      if (banner) {
        banner.classList.add('show');
        setTimeout(() => banner.classList.remove('show'), 2500);
      }
    }

    pollActive();
    setInterval(pollActive, 1500);
  });
})();
