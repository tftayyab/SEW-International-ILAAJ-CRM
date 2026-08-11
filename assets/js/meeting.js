(function () {
  const { $, $all, api, toast, escapeHtml, debounce } = AppUtil;
  const state = window.MEETING_VIEW || { id: 0, workers: [], patients: [] };
  const filters = {
    workers: { q: '', status: 'all' },
    patients: { q: '', status: 'all' }
  };

  function updateStats() {
    const wOk = state.workers.filter((w) => Number(w.attended) === 1).length;
    const pOk = state.patients.filter((p) => Number(p.attended) === 1).length;
    $('#workersStat').textContent = wOk + ' / ' + state.workers.length;
    $('#patientsStat').textContent = pOk + ' / ' + state.patients.length;
  }

  function matchesFilter(person, type) {
    const f = filters[type === 'worker' ? 'workers' : 'patients'];
    const present = Number(person.attended) === 1;
    if (f.status === 'present' && !present) return false;
    if (f.status === 'absent' && present) return false;

    const q = f.q.trim().toLowerCase();
    if (!q) return true;
    const hay = type === 'worker'
      ? [person.name, person.phone]
      : [person.name, person.number, person.city];
    return hay.some((v) => String(v || '').toLowerCase().includes(q));
  }

  function visiblePeople(type) {
    const list = type === 'worker' ? state.workers : state.patients;
    return list.filter((p) => matchesFilter(p, type));
  }

  function rowHtml(person, type) {
    const present = Number(person.attended) === 1;
    const meta = type === 'worker'
      ? (person.phone ? escapeHtml(person.phone) : '—')
      : `${escapeHtml(person.number || '—')}${person.city ? ' · ' + escapeHtml(person.city) : ''}`;
    const link = type === 'patient'
      ? `<a href="${APP.baseUrl}/pages/patient.php?id=${person.id}">${escapeHtml(person.name)}</a>`
      : escapeHtml(person.name);
    return `
      <div class="attendance-row ${present ? 'is-present' : 'is-absent'}" data-type="${type}" data-id="${person.id}">
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

  function syncFilterUi(scope) {
    const root = document.querySelector(`.attendance-controls[data-scope="${scope}"]`);
    if (!root) return;
    $all('.att-filter', root).forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.status === filters[scope].status);
    });
  }

  function updateHints() {
    [['workers', 'worker'], ['patients', 'patient']].forEach(([scope, type]) => {
      const hint = $('#' + scope + 'FilterHint');
      if (!hint) return;
      const total = state[scope].length;
      const shown = visiblePeople(type).length;
      const f = filters[scope];
      if (!total) {
        hint.textContent = '';
        return;
      }
      const parts = [];
      if (f.status !== 'all') parts.push(f.status);
      if (f.q.trim()) parts.push('“' + f.q.trim() + '”');
      if (parts.length) {
        hint.textContent = 'Showing ' + shown + ' of ' + total + ' (' + parts.join(' · ') + '). Mark all applies to shown rows.';
      } else {
        hint.textContent = total + ' expected. Mark all applies to everyone in this list.';
      }
    });
  }

  function render() {
    const wVisible = visiblePeople('worker');
    const pVisible = visiblePeople('patient');
    const wBox = $('#workersAttendance');
    const pBox = $('#patientsAttendance');

    if (!state.workers.length) {
      wBox.innerHTML = '<div class="empty-state">No workers expected for this meeting.</div>';
    } else if (!wVisible.length) {
      wBox.innerHTML = '<div class="empty-state">No workers match this search/filter.</div>';
    } else {
      wBox.innerHTML = wVisible.map((w) => rowHtml(w, 'worker')).join('');
    }

    if (!state.patients.length) {
      pBox.innerHTML = '<div class="empty-state">No patients expected for this meeting.</div>';
    } else if (!pVisible.length) {
      pBox.innerHTML = '<div class="empty-state">No patients match this search/filter.</div>';
    } else {
      pBox.innerHTML = pVisible.map((p) => rowHtml(p, 'patient')).join('');
    }

    syncFilterUi('workers');
    syncFilterUi('patients');
    updateHints();
    updateStats();
    bindToggles();
  }

  function applyMeeting(meeting) {
    if (!meeting) return;
    state.workers = (meeting.workers || []).map((w) => ({
      id: Number(w.id),
      name: w.name,
      phone: w.phone || '',
      attended: Number(w.attended) || 0
    }));
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
        const type = row.dataset.type;
        const personId = Number(row.dataset.id);
        const attended = btn.dataset.attended === '1';
        const list = type === 'worker' ? state.workers : state.patients;
        const person = list.find((x) => Number(x.id) === personId);
        if (!person || Number(person.attended) === (attended ? 1 : 0)) return;

        btn.disabled = true;
        try {
          const res = await api('meetings.php?action=attendance', {
            method: 'POST',
            body: {
              meeting_id: state.id,
              type,
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

  async function markAll(type, attended) {
    const visible = visiblePeople(type);
    if (!visible.length) {
      toast('Nothing to update for the current filter.');
      return;
    }
    const ids = visible.map((p) => Number(p.id));
    const label = attended ? 'present' : 'absent';
    const scope = type === 'worker' ? 'workers' : 'patients';
    const filtered = filters[scope].status !== 'all' || filters[scope].q.trim();
    const msg = filtered
      ? `Mark ${ids.length} shown ${scope} as ${label}?`
      : `Mark all ${ids.length} ${scope} as ${label}?`;
    if (!confirm(msg)) return;

    try {
      const res = await api('meetings.php?action=attendance_bulk', {
        method: 'POST',
        body: {
          meeting_id: state.id,
          type,
          attended: attended ? 1 : 0,
          person_ids: ids
        }
      });
      applyMeeting(res.meeting);
      render();
      toast('Updated ' + ids.length + ' ' + scope + '.');
    } catch (e) {
      toast(e.message);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const liveWorkers = debounce(() => {
      filters.workers.q = $('#workersSearch').value;
      render();
    }, 180);
    const livePatients = debounce(() => {
      filters.patients.q = $('#patientsSearch').value;
      render();
    }, 180);

    $('#workersSearch').addEventListener('input', liveWorkers);
    $('#patientsSearch').addEventListener('input', livePatients);

    document.querySelectorAll('.attendance-controls').forEach((root) => {
      const scope = root.dataset.scope;
      $all('.att-filter', root).forEach((btn) => {
        btn.addEventListener('click', () => {
          filters[scope].status = btn.dataset.status;
          render();
        });
      });
    });

    $('#workersMarkPresent').addEventListener('click', () => markAll('worker', true));
    $('#workersMarkAbsent').addEventListener('click', () => markAll('worker', false));
    $('#patientsMarkPresent').addEventListener('click', () => markAll('patient', true));
    $('#patientsMarkAbsent').addEventListener('click', () => markAll('patient', false));

    render();
  });
})();
