(function () {
  const { $, api, toast, escapeHtml, formatDate, truncate } = AppUtil;

  function bars(rows, container) {
    if (!rows || !rows.length) {
      container.innerHTML = '<div class="muted">No data.</div>';
      return;
    }
    const max = Math.max(...rows.map((r) => Number(r.total) || 0), 1);
    container.innerHTML = rows.map((r) => {
      const pct = Math.round((Number(r.total) / max) * 100);
      return `<div class="chart-row">
        <div title="${escapeHtml(r.label)}">${escapeHtml(truncate(r.label, 14))}</div>
        <div class="chart-bar-track"><div class="chart-bar-fill" style="width:${pct}%"></div></div>
        <div>${r.total}</div>
      </div>`;
    }).join('');
  }

  async function load() {
    const res = await api('dashboard.php');
    const t = res.stats.totals;
    $('#dashStats').innerHTML = `
      <div class="stat-card"><div class="label">Total patients</div><div class="value">${t.patients}</div></div>
      <div class="stat-card"><div class="label">New (7 days)</div><div class="value">${t.new_patients_7}</div></div>
      <div class="stat-card"><div class="label">New (30 days)</div><div class="value">${t.new_patients_30}</div></div>
      <div class="stat-card"><div class="label">Total messages</div><div class="value">${t.messages}</div></div>
      <div class="stat-card"><div class="label">Patient messages</div><div class="value">${t.patient_messages}</div></div>
      <div class="stat-card"><div class="label">Ameer responses</div><div class="value">${t.ameer_messages}</div></div>
      <div class="stat-card"><div class="label">Workers</div><div class="value">${t.workers}</div></div>
      <div class="stat-card"><div class="label">Meetings</div><div class="value">${t.meetings}</div></div>
      <div class="stat-card"><div class="label">With images</div><div class="value">${t.with_images}</div></div>
      <div class="stat-card"><div class="label">Without images</div><div class="value">${t.without_images}</div></div>
      <div class="stat-card"><div class="label">Excel imports</div><div class="value">${t.imports}</div></div>
    `;

    bars(res.stats.by_country, $('#chartCountry'));
    bars(res.stats.by_city, $('#chartCity'));
    bars(res.stats.by_occupation, $('#chartOccupation'));

    const meetings = res.stats.recent_meetings;
    $('#recentMeetings').innerHTML = meetings.length
      ? `<ul>${meetings.map((m) => `<li><a href="${APP.baseUrl}/pages/meeting.php?id=${m.id}">${escapeHtml(m.name)}</a> — ${escapeHtml(formatDate(m.meeting_date) || '—')}</li>`).join('')}</ul>`
      : '<div class="muted">No meetings yet.</div>';

    $('#recentPatients').innerHTML = res.stats.recent_patients.length
      ? `<ul>${res.stats.recent_patients.map((p) => `<li><a href="${APP.baseUrl}/pages/patient.php?id=${p.id}">${escapeHtml(p.name)}</a> — ${escapeHtml(p.number)}</li>`).join('')}</ul>`
      : '<div class="muted">No patients yet.</div>';

    $('#recentMessages').innerHTML = res.stats.recent_messages.length
      ? `<ul>${res.stats.recent_messages.map((m) => `<li><a href="${APP.baseUrl}/pages/patient.php?id=${m.patient_id}">${escapeHtml(m.patient_name)}</a>: ${escapeHtml(truncate(m.message_text, 70))}</li>`).join('')}</ul>`
      : '<div class="muted">No conversations yet.</div>';
  }

  document.addEventListener('DOMContentLoaded', () => {
    load().catch((e) => toast(e.message));
  });
})();
