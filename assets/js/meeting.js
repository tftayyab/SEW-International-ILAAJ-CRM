(function () {
  const { $, $all, api, toast, escapeHtml, debounce, copyText } = AppUtil;
  const state = window.MEETING_VIEW || { id: 0, patients: [] };
  const filter = { q: '', status: 'all' };

  function updateStats() {
    const pOk = state.patients.filter((p) => Number(p.attended) === 1).length;
    $('#patientsStat').textContent = pOk + ' / ' + state.patients.length;
  }

  function matchesFilter(person) {
    const present = Number(person.attended) === 1;
    if (filter.status === 'present' && !present) return false;
    if (filter.status === 'absent' && present) return false;

    const q = filter.q.trim().toLowerCase();
    if (!q) return true;
    return [person.name, person.number, person.city]
      .some((v) => String(v || '').toLowerCase().includes(q));
  }

  function visiblePeople() {
    return state.patients.filter(matchesFilter);
  }

  function rowHtml(person) {
    const present = Number(person.attended) === 1;
    const meta = `${escapeHtml(person.number || '—')}${person.city ? ' · ' + escapeHtml(person.city) : ''}`;
    const link = `<a href="${APP.baseUrl}/pages/patient.php?id=${person.id}">${escapeHtml(person.name)}</a>`;
    return `
      <div class="attendance-row ${present ? 'is-present' : 'is-absent'}" data-id="${person.id}">
        <div class="attendance-info">
          <div class="attendance-name">${link}</div>
          <div class="attendance-meta">${meta}</div>
        </div>
        <div class="attendance-toggle" role="group" aria-label="Attendance">
          <button type="button" class="att-btn ${present ? 'active present' : ''}" data-attended="1">Present</button>
          <button type="button" class="att-btn ${!present ? 'active absent' : ''}" data-attended="0">Absent</button>
        </div>
      </div>`;
  }

  function syncFilterUi() {
    const root = document.querySelector('.attendance-controls[data-scope="patients"]');
    if (!root) return;
    $all('.att-filter', root).forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.status === filter.status);
    });
  }

  function updateHints() {
    const hint = $('#patientsFilterHint');
    if (!hint) return;
    const total = state.patients.length;
    const shown = visiblePeople().length;
    if (!total) {
      hint.textContent = '';
      return;
    }
    const parts = [];
    if (filter.status !== 'all') parts.push(filter.status);
    if (filter.q.trim()) parts.push('“' + filter.q.trim() + '”');
    if (parts.length) {
      hint.textContent = 'Showing ' + shown + ' of ' + total + ' (' + parts.join(' · ') + '). Mark all applies to shown rows.';
    } else {
      hint.textContent = total + ' expected. Mark all applies to everyone in this list.';
    }
  }

  function render() {
    const visible = visiblePeople();
    const box = $('#patientsAttendance');

    if (!state.patients.length) {
      box.innerHTML = '<div class="empty-state">No patients expected for this meeting.</div>';
    } else if (!visible.length) {
      box.innerHTML = '<div class="empty-state">No patients match this search/filter.</div>';
    } else {
      box.innerHTML = visible.map(rowHtml).join('');
    }

    syncFilterUi();
    updateHints();
    updateStats();
    bindToggles();
  }

  function applyMeeting(meeting) {
    if (!meeting) return;
    state.patients = (meeting.patients || []).map((p) => ({
      id: Number(p.id),
      name: p.name,
      number: p.number || '',
      city: p.city || '',
      attended: Number(p.attended) || 0
    }));
  }

  function bindToggles() {
    document.querySelectorAll('.attendance-row .att-btn').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const row = btn.closest('.attendance-row');
        const personId = Number(row.dataset.id);
        const attended = btn.dataset.attended === '1';
        const person = state.patients.find((x) => Number(x.id) === personId);
        if (!person || Number(person.attended) === (attended ? 1 : 0)) return;

        btn.disabled = true;
        try {
          const res = await api('meetings.php?action=attendance', {
            method: 'POST',
            body: {
              meeting_id: state.id,
              person_id: personId,
              attended: attended ? 1 : 0
            }
          });
          applyMeeting(res.meeting);
          render();
        } catch (e) {
          toast(e.message);
          btn.disabled = false;
        }
      });
    });
  }

  async function markAll(attended) {
    const visible = visiblePeople();
    if (!visible.length) {
      toast('Nothing to update for the current filter.');
      return;
    }
    const ids = visible.map((p) => Number(p.id));
    const label = attended ? 'present' : 'absent';
    const filtered = filter.status !== 'all' || filter.q.trim();
    const msg = filtered
      ? `Mark ${ids.length} shown patients as ${label}?`
      : `Mark all ${ids.length} patients as ${label}?`;
    if (!confirm(msg)) return;

    try {
      const res = await api('meetings.php?action=attendance_bulk', {
        method: 'POST',
        body: {
          meeting_id: state.id,
          attended: attended ? 1 : 0,
          person_ids: ids
        }
      });
      applyMeeting(res.meeting);
      render();
      toast('Updated ' + ids.length + ' patients.');
    } catch (e) {
      toast(e.message);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const live = debounce(() => {
      filter.q = $('#patientsSearch').value;
      render();
    }, 180);

    $('#patientsSearch').addEventListener('input', live);

    document.querySelectorAll('.attendance-controls .att-filter').forEach((btn) => {
      btn.addEventListener('click', () => {
        filter.status = btn.dataset.status;
        render();
      });
    });

    $('#patientsMarkPresent').addEventListener('click', () => markAll(true));
    $('#patientsMarkAbsent').addEventListener('click', () => markAll(false));

    $all('[data-copy]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        try {
          await copyText(btn.dataset.copy);
          toast('Link copied.');
        } catch (err) {
          toast(err.message || 'Could not copy.');
        }
      });
    });

    render();
  });
})();
