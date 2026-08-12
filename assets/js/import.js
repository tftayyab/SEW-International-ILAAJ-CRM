(function () {
  const { $, $all, api, toast, escapeHtml } = AppUtil;

  let preview = null;
  let resolutions = {};
  let noneSelected = false;

  async function loadHistory() {
    const res = await api('import.php?action=history');
    const box = $('#importHistory');
    if (!res.imports.length) {
      box.innerHTML = '<div class="empty-state">No imports yet.</div>';
      return;
    }
    box.innerHTML = `
      <div class="table-wrap">
        <table class="data-table" style="min-width:640px">
          <thead><tr><th>File</th><th>Status</th><th>Rows</th><th>New patients</th><th>Messages</th><th>When</th></tr></thead>
          <tbody>
            ${res.imports.map((i) => `
              <tr>
                <td>${escapeHtml(i.filename)}</td>
                <td>${escapeHtml(i.status)}</td>
                <td>${i.imported_rows}/${i.total_rows}</td>
                <td>${i.new_patients}</td>
                <td>${i.messages_created}</td>
                <td>${escapeHtml(i.completed_at || i.created_at)}</td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>`;
  }

  function emptyErrors(r) {
    return !r.errors || !r.errors.length;
  }

  function needsDecision(r) {
    return emptyErrors(r) && ((r.existing_matches && r.existing_matches.length) || r.file_duplicate_number);
  }

  function renderPreview() {
    const p = preview;
    noneSelected = false;
    $('#previewCard').hidden = false;
    $('#previewSummary').innerHTML = `
      <div class="stat-card tone-sky"><div class="label">Rows</div><div class="value">${p.total_rows}</div></div>
      <div class="stat-card tone-mint"><div class="label">Valid</div><div class="value">${p.valid_rows}</div></div>
      <div class="stat-card tone-pink"><div class="label">Invalid</div><div class="value value-danger">${p.invalid_rows}</div></div>
      <div class="stat-card tone-lavender"><div class="label">Messages</div><div class="value">${p.messages_detected}</div></div>
      <div class="stat-card tone-yellow"><div class="label">Need decision</div><div class="value value-warm">${p.needs_resolution_count}</div></div>
    `;

    let errHtml = '';
    if (p.errors && p.errors.length) {
      errHtml += `<div class="alert alert-error"><strong>Errors</strong><ul>${p.errors.map((e) => `<li>${escapeHtml(e)}</li>`).join('')}</ul></div>`;
    }
    if (p.warnings && p.warnings.length) {
      errHtml += `<div class="alert alert-warning"><strong>Warnings</strong><ul>${p.warnings.slice(0, 20).map((e) => `<li>${escapeHtml(e)}</li>`).join('')}</ul></div>`;
    }
    $('#previewErrors').innerHTML = errHtml;

    const need = (p.rows || []).filter(needsDecision);
    const section = $('#resolutionSection');
    resolutions = {};

    // Auto-create rows that don't need a decision
    (p.rows || []).forEach((r) => {
      if (emptyErrors(r) && !needsDecision(r)) {
        resolutions[r.row_number] = { action: 'create_new' };
      }
    });

    if (!need.length) {
      section.innerHTML = '<div class="alert alert-info">No duplicate-number decisions required. Valid rows will be created.</div>';
      return;
    }

    // Also bump needs_resolution to include file duplicates in count display if needed
    section.innerHTML = `
      <h3>Same number — choose who to create</h3>
      <p class="muted">Tick the people you want to create. Choose <strong>None</strong> to create nobody from this list.</p>
      <label class="import-none-box">
        <input type="checkbox" id="importNone">
        <strong>None</strong> — do not create any of the people listed below
      </label>
      <div class="import-choice-list" id="resList"></div>
      <p id="resError" class="alert alert-error" hidden>Select at least one person to create, or choose None.</p>
    `;

    $('#resList').innerHTML = need.map((r) => {
      const existingNote = (r.existing_matches && r.existing_matches.length)
        ? `<div class="muted" style="margin-top:0.35rem">Already in system with this number: ${r.existing_matches.map((m) => escapeHtml(m.name)).join(', ')}</div>`
        : '';
      return `<label class="import-choice selected" data-row="${r.row_number}">
        <input type="checkbox" class="import-create" value="${r.row_number}" checked>
        <span>
          <strong>${escapeHtml(r.patient.name)}</strong> — ${escapeHtml(r.patient.number)}
          <div class="muted">Mother: ${escapeHtml(r.patient.mother_name || '—')} · ${escapeHtml(r.patient.city || '—')} · ${escapeHtml(r.patient.occupation || '—')} · Row ${r.row_number} · ${r.message_count} messages</div>
          ${existingNote}
        </span>
      </label>`;
    }).join('');

    const none = $('#importNone');
    none.addEventListener('change', () => {
      noneSelected = none.checked;
      if (none.checked) {
        $all('.import-create').forEach((c) => { c.checked = false; });
        $all('.import-choice').forEach((c) => c.classList.remove('selected'));
      }
    });
    $all('.import-create').forEach((c) => {
      c.addEventListener('change', () => {
        if (c.checked) {
          none.checked = false;
          noneSelected = false;
        }
        c.closest('.import-choice').classList.toggle('selected', c.checked);
      });
    });
  }

  function collectResolutions() {
    const need = (preview.rows || []).filter(needsDecision);
    if (!need.length) return true;

    const err = $('#resError');
    const checked = $all('.import-create:checked').map((c) => parseInt(c.value, 10));
    const none = $('#importNone')?.checked;

    if (!none && checked.length === 0) {
      if (err) err.hidden = false;
      return false;
    }
    if (err) err.hidden = true;

    need.forEach((r) => {
      if (none) {
        resolutions[r.row_number] = { action: 'none' };
      } else if (checked.includes(r.row_number)) {
        resolutions[r.row_number] = { action: 'create_new' };
      } else {
        resolutions[r.row_number] = { action: 'skip' };
      }
    });
    return true;
  }

  document.addEventListener('DOMContentLoaded', () => {
    $('#importForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const file = $('#importFile').files[0];
      if (!file) {
        toast('Please choose a file.');
        return;
      }
      const fd = new FormData();
      fd.append('file', file);
      fd.append('action', 'preview');
      try {
        $('#btnPreview').disabled = true;
        $('#btnPreview').textContent = 'Processing…';
        const res = await api('import.php?action=preview', { method: 'POST', body: fd });
        preview = res.preview;
        // Expand needs_resolution_count for file duplicates too
        const needCount = (preview.rows || []).filter(needsDecision).length;
        preview.needs_resolution_count = needCount;
        renderPreview();
        toast('Preview ready.');
      } catch (err) {
        toast(err.message);
      } finally {
        $('#btnPreview').disabled = false;
        $('#btnPreview').textContent = 'Preview Import';
      }
    });

    $('#btnConfirmImport').addEventListener('click', async () => {
      if (!preview) return;
      if (!collectResolutions()) {
        toast('Select who to create, or choose None.');
        return;
      }
      try {
        const res = await api('import.php?action=confirm', {
          method: 'POST',
          body: { import_id: preview.import_id, resolutions }
        });
        toast(`Import complete: ${res.result.imported_rows} rows, ${res.result.messages_created} messages.`);
        $('#previewCard').hidden = true;
        preview = null;
        $('#importFile').value = '';
        await loadHistory();
      } catch (err) {
        toast(err.message);
      }
    });

    $('#btnCancelImport').addEventListener('click', async () => {
      if (!preview) return;
      try {
        await api('import.php?action=cancel', { method: 'POST', body: { import_id: preview.import_id } });
      } catch (e) { /* ignore */ }
      preview = null;
      $('#previewCard').hidden = true;
      toast('Import cancelled.');
    });

    loadHistory().catch((e) => toast(e.message));
  });
})();
