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

  function statCard({ tone = 'mint', label, value, foot = '', valueClass = '' }) {
    return `<div class="stat-card tone-${tone}">
      <div class="label">${escapeHtml(label)}</div>
      <div class="value ${valueClass}">${value}</div>
      ${foot ? `<div class="foot">${foot}</div>` : ''}
    </div>`;
  }

  async function load() {
    const res = await api('dashboard.php');
    const t = res.stats.totals;

    $('#statsPatients').innerHTML = [
      statCard({
        tone: 'mint',
        label: 'Total patients',
        value: t.patients,
        foot: `<strong>${t.with_images}</strong> with photos &middot; <strong>${t.without_images}</strong> without`,
      }),
      statCard({
        tone: 'yellow',
        label: 'New in 7 days',
        value: t.new_patients_7,
        foot: 'Recent arrivals this week',
      }),
      statCard({
        tone: 'peach',
        label: 'New in 30 days',
        value: t.new_patients_30,
        foot: 'Growth over the last month',
      }),
    ].join('');

    $('#statsMessages').innerHTML = [
      statCard({
        tone: 'sky',
        label: 'Total messages',
        value: t.messages,
        foot: 'Across every patient thread',
      }),
      statCard({
        tone: 'lavender',
        label: 'Patient messages',
        value: t.patient_messages,
        foot: 'Concerns and follow-ups sent in',
      }),
      statCard({
        tone: 'mint',
        label: 'Ameer Sahab responses',
        value: t.ameer_messages,
        foot: 'Guidance shared by the advisor',
      }),
    ].join('');

    $('#statsOps').innerHTML = [
      statCard({
        tone: 'pink',
        label: 'Pending replies',
        value: t.pending_replies,
        valueClass: 'value-warm',
        foot: 'Patients waiting for Ameer Sahab',
      }),
      statCard({
        tone: 'sky',
        label: 'Meetings',
        value: t.meetings,
        foot: 'Total sessions on record',
      }),
      statCard({
        tone: 'yellow',
        label: 'Excel imports',
        value: t.imports,
        foot: 'Historical sheets processed',
      }),
      statCard({
        tone: 'lavender',
        label: 'Media coverage',
        value: `${t.with_images}<span style="font-size:0.9rem;color:var(--ink-muted);font-weight:500;letter-spacing:0"> / ${t.patients}</span>`,
        foot: `<strong>${t.without_images}</strong> patients without any photo`,
      }),
    ].join('');

    bars(res.stats.by_country, $('#chartCountry'));
    bars(res.stats.by_city, $('#chartCity'));
    bars(res.stats.by_occupation, $('#chartOccupation'));

    const meetings = res.stats.recent_meetings;
    $('#recentMeetings').innerHTML = meetings.length
      ? `<ul>${meetings.map((m) => `<li><a href="${APP.baseUrl}/pages/meeting.php?id=${m.id}">${escapeHtml(m.name)}</a> — ${escapeHtml(formatDate(m.meeting_date) || '—')}</li>`).join('')}</ul>`
      : '<div class="empty-state">No meetings yet.</div>';

    $('#recentPatients').innerHTML = res.stats.recent_patients.length
      ? `<ul>${res.stats.recent_patients.map((p) => `<li><a href="${APP.baseUrl}/pages/patient.php?id=${p.id}">${escapeHtml(p.name)}</a> — ${escapeHtml(p.number)}</li>`).join('')}</ul>`
      : '<div class="empty-state">No patients yet.</div>';

    $('#recentMessages').innerHTML = res.stats.recent_messages.length
      ? `<ul>${res.stats.recent_messages.map((m) => `<li><a href="${APP.baseUrl}/pages/patient.php?id=${m.patient_id}">${escapeHtml(m.patient_name)}</a>: ${escapeHtml(truncate(m.message_text, 70))}</li>`).join('')}</ul>`
      : '<div class="empty-state">No conversations yet.</div>';
  }

  document.addEventListener('DOMContentLoaded', () => {
    load().catch((e) => toast(e.message));
  });
})();
