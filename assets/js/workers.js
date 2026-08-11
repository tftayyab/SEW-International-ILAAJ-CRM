(function () {
  const { $, api, toast, escapeHtml, openModal, closeModal, debounce, icons } = AppUtil;

  async function load(q) {
    const params = new URLSearchParams({ action: 'list' });
    if (q) params.set('q', q);
    const res = await api('workers.php?' + params.toString());
    const box = $('#workersTable');
    if (!res.workers.length) {
      box.innerHTML = '<div class="empty-state">No workers found.</div>';
      return;
    }
    box.innerHTML = `
      <div class="table-wrap">
        <table class="data-table" style="min-width:420px">
          <thead><tr><th>Name</th><th>Phone number</th><th></th></tr></thead>
          <tbody>
            ${res.workers.map((w) => `
              <tr>
                <td class="cell-primary">${escapeHtml(w.name)}</td>
                <td>${escapeHtml(w.phone || '—')}</td>
                <td>
                  <div class="icon-actions">
                    <button class="icon-btn" data-edit="${w.id}" title="Edit">${icons.edit}</button>
                    <button class="icon-btn icon-danger" data-del="${w.id}" title="Delete">${icons.trash}</button>
                  </div>
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>`;
    box.querySelectorAll('[data-edit]').forEach((btn) => {
      const w = res.workers.find((x) => String(x.id) === btn.dataset.edit);
      btn.addEventListener('click', () => openForm(w));
    });
    box.querySelectorAll('[data-del]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('Delete this worker?')) return;
        try {
          await api('workers.php?action=delete', { method: 'POST', body: { id: btn.dataset.del } });
          toast('Worker deleted.');
          await load($('#workerSearch').value.trim());
        } catch (e) { toast(e.message); }
      });
    });
  }

  function openForm(w) {
    w = w || {};
    openModal(`
      <div class="modal-header"><h2>${w.id ? 'Edit worker' : 'Add worker'}</h2>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button></div>
      <div class="modal-body">
        <form id="workerForm">
          <input type="hidden" name="id" value="${w.id || ''}">
          <div class="field"><label>Name *</label><input name="name" required value="${escapeHtml(w.name || '')}"></div>
          <div class="field" style="margin-top:0.75rem"><label>Phone number</label><input name="phone" value="${escapeHtml(w.phone || '')}"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="button" class="btn" id="saveWorker">Save</button>
      </div>`);
    $('#saveWorker').addEventListener('click', async () => {
      const data = Object.fromEntries(new FormData($('#workerForm')).entries());
      try {
        if (data.id) await api('workers.php?action=update', { method: 'POST', body: data });
        else await api('workers.php?action=create', { method: 'POST', body: data });
        closeModal();
        toast('Worker saved.');
        await load($('#workerSearch').value.trim());
      } catch (e) { toast(e.message); }
    });
  }

  const live = debounce(() => load($('#workerSearch').value.trim()).catch((e) => toast(e.message)), 280);

  document.addEventListener('DOMContentLoaded', () => {
    $('#btnAddWorker').addEventListener('click', () => openForm(null));
    $('#workerSearch').addEventListener('input', live);
    $('#btnWorkerSearch').addEventListener('click', () => load($('#workerSearch').value.trim()).catch((e) => toast(e.message)));
    load().catch((e) => toast(e.message));
  });
})();
