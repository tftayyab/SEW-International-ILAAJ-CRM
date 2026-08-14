(function () {
  const { $, $all, api, toast, escapeHtml, formatDate, truncate, debounce } = AppUtil;

  let page = 1;
  const isAmeer = !!APP.isAmeer;
  const patientHref = (id) => isAmeer
    ? (APP.baseUrl + '/pages/advisor.php?patient=' + id)
    : (APP.baseUrl + '/pages/patient.php?id=' + id);

  async function load() {
    const params = new URLSearchParams({
      action: 'pending',
      page,
      per_page: 24
    });
    const q = $('#pendingSearch').value.trim();
    if (q) params.set('q', q);

    const res = await api('patients.php?' + params.toString());
    const box = $('#pendingCards');
    const rows = res.data || [];

    if (!rows.length) {
      box.innerHTML = '<div class="empty-state">' +
        (isAmeer ? 'No patients are waiting for your reply.' : 'No patients are awaiting a reply. Nicely done.') +
        '</div>';
    } else {
      // No profile photos on cards — photo only when the patient page opens
      const tones = ['tone-peach', 'tone-mint', 'tone-sky', 'tone-yellow'];
      box.innerHTML = rows.map((p, i) => {
        const dateText = p.last_message_date
          ? escapeHtml(formatDate(p.last_message_date))
          : 'No date';
        return `
          <a class="info-card ${tones[i % tones.length]}" href="${patientHref(p.id)}">
            <div class="info-card__top">
              <span class="info-card__status">Awaiting reply</span>
              <span class="info-card__date">${dateText}</span>
            </div>
            <h3 class="info-card__name">${escapeHtml(p.name)}</h3>
            ${p.mother_name ? `<p class="info-card__sub">Mother: ${escapeHtml(p.mother_name)}</p>` : ''}
            <p class="info-card__msg">${escapeHtml(truncate(p.last_message || 'No message text.', 220))}</p>
            <span class="info-card__cta">Open conversation →</span>
          </a>`;
      }).join('');
    }

    const pg = $('#pendingPagination');
    const pgn = res.pagination;
    pg.innerHTML = `
      <button class="btn btn-secondary btn-sm" ${pgn.page <= 1 ? 'disabled' : ''} data-page="${pgn.page - 1}">Prev</button>
      <span>Page ${pgn.page} / ${pgn.total_pages}</span>
      <button class="btn btn-secondary btn-sm" ${pgn.page >= pgn.total_pages ? 'disabled' : ''} data-page="${pgn.page + 1}">Next</button>`;
    $all('[data-page]', pg).forEach((b) => b.addEventListener('click', () => {
      page = parseInt(b.dataset.page, 10);
      load().catch((e) => toast(e.message));
    }));
  }

  const live = debounce(() => {
    page = 1;
    load().catch((e) => toast(e.message));
  }, 260);

  document.addEventListener('DOMContentLoaded', () => {
    $('#pendingSearch').addEventListener('input', live);
    load().catch((e) => toast(e.message));
  });
})();
