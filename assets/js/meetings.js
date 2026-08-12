(function () {
  const { $, $all, api, toast, escapeHtml, formatDate, debounce, icons } = AppUtil;
  let page = 1;

  async function loadMeetings() {
    const q = $('#meetingSearch').value.trim();
    const params = new URLSearchParams({ action: 'list', page, per_page: 20 });
    if (q) params.set('q', q);
    const res = await api('meetings.php?' + params.toString());
    const box = $('#meetingsTable');
    if (!res.data.length) {
      box.innerHTML = '<div class="empty-state">No meetings found.</div>';
      $('#meetingsPagination').innerHTML = '';
      return;
    }
    box.innerHTML = `
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Name</th><th>Date</th><th>Location</th><th>Patients</th><th class="no-sort"></th></tr></thead>
          <tbody>
            ${res.data.map((m) => `
              <tr class="row-link" data-href="${APP.baseUrl}/pages/meeting.php?id=${m.id}">
                <td><div class="cell-primary">${escapeHtml(m.name)}</div></td>
                <td>${escapeHtml(formatDate(m.meeting_date) || '—')}</td>
                <td>${escapeHtml(m.location || '—')}</td>
                <td>${m.patients_attended || 0}/${m.patients_count || 0}</td>
                <td>
                  <div class="icon-actions" onclick="event.stopPropagation()">
                    <a class="icon-btn" href="${APP.baseUrl}/pages/meeting_form.php?id=${m.id}" title="Edit">${icons.edit}</a>
                    <button class="icon-btn icon-danger" data-del="${m.id}" title="Delete">${icons.trash}</button>
                  </div>
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>`;
    $all('.row-link', box).forEach((row) => {
      row.addEventListener('click', () => { window.location.href = row.dataset.href; });
    });
    $all('[data-del]', box).forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('Delete this meeting?')) return;
        try {
          await api('meetings.php?action=delete', { method: 'POST', body: { id: btn.dataset.del } });
          toast('Meeting deleted.');
          await loadMeetings();
        } catch (e) { toast(e.message); }
      });
    });
    const p = res.pagination;
    $('#meetingsPagination').innerHTML = `
      <button class="btn btn-secondary btn-sm" ${p.page <= 1 ? 'disabled' : ''} data-page="${p.page - 1}">Prev</button>
      <span>Page ${p.page} / ${p.total_pages}</span>
      <button class="btn btn-secondary btn-sm" ${p.page >= p.total_pages ? 'disabled' : ''} data-page="${p.page + 1}">Next</button>`;
    $all('[data-page]', $('#meetingsPagination')).forEach((b) => {
      b.addEventListener('click', () => { page = parseInt(b.dataset.page, 10); loadMeetings().catch((e) => toast(e.message)); });
    });
  }

  const live = debounce(() => { page = 1; loadMeetings().catch((e) => toast(e.message)); }, 280);

  document.addEventListener('DOMContentLoaded', () => {
    $('#btnAddMeeting').addEventListener('click', () => {
      window.location.href = APP.baseUrl + '/pages/meeting_form.php';
    });
    $('#meetingSearch').addEventListener('input', live);
    $('#btnMeetingSearch').addEventListener('click', () => { page = 1; loadMeetings().catch((e) => toast(e.message)); });
    loadMeetings().catch((e) => toast(e.message));
  });
})();
