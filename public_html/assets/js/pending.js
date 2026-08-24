(function () {
  const {
    $, $all, api, toast, escapeHtml, formatDate, truncate, debounce,
    LIST_PER_PAGE, readListContext, syncListUrl, patientDetailUrl, advisorDetailUrl
  } = AppUtil;

  const isAmeer = !!APP.isAmeer;
  let ctx = readListContext('pending');

  function patientHref(id) {
    return isAmeer
      ? advisorDetailUrl(id, ctx)
      : patientDetailUrl(id, ctx);
  }

  async function load() {
    syncListUrl('/pages/pending.php', ctx);
    if (ctx.q) $('#pendingSearch').value = ctx.q;

    const params = new URLSearchParams({
      action: 'pending',
      page: ctx.page,
      per_page: LIST_PER_PAGE
    });
    if (ctx.q) params.set('q', ctx.q);

    const res = await api('patients.php?' + params.toString());
    const box = $('#pendingCards');
    const rows = res.data || [];

    if (!rows.length) {
      box.innerHTML = '<div class="empty-state">' +
        (isAmeer ? 'No patients are waiting for your reply.' : 'No patients are awaiting a reply. Nicely done.') +
        '</div>';
    } else {
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
      <span>Page ${pgn.page} / ${pgn.total_pages} (${pgn.total})</span>
      <button class="btn btn-secondary btn-sm" ${pgn.page >= pgn.total_pages ? 'disabled' : ''} data-page="${pgn.page + 1}">Next</button>`;
    $all('[data-page]', pg).forEach((b) => b.addEventListener('click', () => {
      ctx.page = parseInt(b.dataset.page, 10);
      load().catch((e) => toast(e.message));
    }));
  }

  const live = debounce(() => {
    ctx.page = 1;
    ctx.q = $('#pendingSearch').value.trim();
    load().catch((e) => toast(e.message));
  }, 260);

  document.addEventListener('DOMContentLoaded', () => {
    $('#pendingSearch').addEventListener('input', live);
    load().catch((e) => toast(e.message));
  });
})();
