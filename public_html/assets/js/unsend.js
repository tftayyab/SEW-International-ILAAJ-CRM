(function () {
  const { $, $all, api, toast, escapeHtml, debounce, withView, LIST_PER_PAGE, readListContext, syncListUrl, openWhatsApp } = AppUtil;

  let ctx = readListContext('unsend');
  let rowById = {};

  function patientUrl(id) {
    return withView((APP.baseUrl || '') + '/pages/patient.php?id=' + id + '&from=unsend');
  }

  async function load() {
    syncListUrl('/pages/unsend.php', ctx);
    if (ctx.q) $('#unsendSearch').value = ctx.q;

    const params = new URLSearchParams({
      action: 'unsent',
      page: ctx.page,
      per_page: LIST_PER_PAGE,
    });
    if (ctx.q) params.set('q', ctx.q);

    const res = await api('patients.php?' + params.toString());
    const box = $('#unsendTable');
    const rows = res.data || [];
    rowById = {};
    rows.forEach((p) => {
      rowById[String(p.id)] = {
        number: p.number || '',
        text: p.response_text || '',
      };
    });

    if (!rows.length) {
      box.innerHTML = '<div class="empty-state">No unsent responses.</div>';
    } else {
      box.innerHTML = `
        <div class="table-wrap">
          <table class="data-table unsend-table">
            <thead><tr>
              <th>Name</th>
              <th>Number</th>
              <th>Response</th>
              <th class="no-sort"></th>
            </tr></thead>
            <tbody>
              ${rows.map((p) => `
                <tr data-id="${p.id}">
                  <td><a href="${patientUrl(p.id)}">${escapeHtml(p.name)}</a></td>
                  <td>${escapeHtml(p.number)}</td>
                  <td><div class="unsend-response">${escapeHtml(p.response_text || '—')}</div></td>
                  <td>
                    <button type="button" class="btn btn-sm" data-send="${p.id}">Send</button>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;

      $all('[data-send]', box).forEach((btn) => {
        btn.addEventListener('click', () => sendResponse(btn));
      });
    }

    const pg = res.pagination;
    $('#unsendPagination').innerHTML = `
      <button class="btn btn-secondary btn-sm" ${pg.page <= 1 ? 'disabled' : ''} data-page="${pg.page - 1}">Prev</button>
      <span>Page ${pg.page} / ${pg.total_pages} (${pg.total})</span>
      <button class="btn btn-secondary btn-sm" ${pg.page >= pg.total_pages ? 'disabled' : ''} data-page="${pg.page + 1}">Next</button>`;
    $all('[data-page]', $('#unsendPagination')).forEach((b) => {
      b.addEventListener('click', () => {
        ctx.page = parseInt(b.dataset.page, 10);
        load().catch((e) => toast(e.message));
      });
    });
  }

  async function sendResponse(btn) {
    const id = parseInt(btn.dataset.send, 10);
    const row = rowById[String(id)] || {};
    const number = row.number || '';
    const text = row.text || '';
    btn.disabled = true;
    btn.textContent = 'Sending…';
    try {
      await api('patients.php?action=set_response_sent', { method: 'POST', body: { id, sent: true } });
      toast('Response marked as sent.');
      if (!text) {
        toast('No Ameer Sahab message to send on WhatsApp.');
      } else {
        openWhatsApp(number, text);
      }
      await load();
    } catch (e) {
      toast(e.message);
      btn.disabled = false;
      btn.textContent = 'Send';
    }
  }

  const live = debounce(() => {
    ctx.page = 1;
    ctx.q = $('#unsendSearch').value.trim();
    load().catch((e) => toast(e.message));
  }, 280);

  document.addEventListener('DOMContentLoaded', () => {
    ctx = readListContext('unsend');
    $('#unsendSearch').addEventListener('input', live);
    $('#btnUnsendSearch').addEventListener('click', () => {
      ctx.page = 1;
      ctx.q = $('#unsendSearch').value.trim();
      load().catch((e) => toast(e.message));
    });
    load().catch((e) => toast(e.message));
  });
})();
