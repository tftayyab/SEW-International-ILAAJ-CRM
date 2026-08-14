(function () {
  const { $, api, toast, escapeHtml, formatDate, truncate } = AppUtil;
  const isEditor = !!APP.isEditor;

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

  function filterParams() {
    const period = $('#dashPeriod').value;
    const params = { period };
    if (period === 'year') params.year = $('#dashYear').value;
    if (period === 'custom') {
      params.from = $('#dashFrom').value;
      params.to = $('#dashTo').value;
    }
    return params;
  }

  function syncFilterFields() {
    const period = $('#dashPeriod').value;
    $('#dashYear').hidden = period !== 'year';
    $('#dashFrom').hidden = period !== 'custom';
    $('#dashTo').hidden = period !== 'custom';
  }

  function fillYears(years, selected) {
    const sel = $('#dashYear');
    const current = selected || sel.value || String(new Date().getFullYear());
    const list = (years && years.length) ? years : [Number(current)];
    sel.innerHTML = list.map((y) => `<option value="${y}">${y}</option>`).join('');
    sel.value = list.map(String).includes(String(current)) ? String(current) : String(list[0]);
  }

  async function load() {
    const params = new URLSearchParams(filterParams());
    const res = await api('dashboard.php?' + params.toString());
    const t = res.stats.totals;
    const filter = res.stats.filter || { period: 'all', label: 'All time' };
    const scoped = filter.period && filter.period !== 'all';
    fillYears(filter.years, filter.year);

    const patientCards = [
      statCard({
        tone: 'mint',
        label: scoped ? 'Patients added' : 'Total patients',
        value: t.patients,
        foot: `<strong>${t.with_images}</strong> with photos &middot; <strong>${t.without_images}</strong> without`,
      }),
    ];
    if (!scoped) {
      patientCards.push(
        statCard({
          tone: 'yellow',
          label: 'New in 7 days',
          value: t.new_patients_7,
        }),
        statCard({
          tone: 'peach',
          label: 'New in 30 days',
          value: t.new_patients_30,
        })
      );
    }

    $('#statsPatients').innerHTML = patientCards.join('');

    $('#statsMessages').innerHTML = [
      statCard({
        tone: 'sky',
        label: 'Total messages',
        value: t.messages,
      }),
      statCard({
        tone: 'lavender',
        label: 'Patient messages',
        value: t.patient_messages,
      }),
      statCard({
        tone: 'mint',
        label: 'Ameer Sahab responses',
        value: t.ameer_messages,
      }),
    ].join('');

    $('#statsOps').innerHTML = [
      statCard({
        tone: 'pink',
        label: 'Pending replies',
        value: t.pending_replies,
        valueClass: 'value-warm',
      }),
      statCard({
        tone: 'sky',
        label: 'Meetings',
        value: t.meetings,
      }),
    ].join('');

    bars(res.stats.by_country, $('#chartCountry'));
    bars(res.stats.by_city, $('#chartCity'));
    bars(res.stats.by_occupation, $('#chartOccupation'));

    const meetings = res.stats.recent_meetings;
    $('#recentMeetings').innerHTML = meetings.length
      ? `<ul>${meetings.map((m) => {
          const label = `${escapeHtml(m.name)} — ${escapeHtml(formatDate(m.meeting_date) || '—')}`;
          return isEditor
            ? `<li><a href="${APP.baseUrl}/pages/meeting.php?id=${m.id}">${label}</a></li>`
            : `<li>${label}</li>`;
        }).join('')}</ul>`
      : '<div class="empty-state">No meetings yet.</div>';
  }

  document.addEventListener('DOMContentLoaded', () => {
    syncFilterFields();
    $('#dashPeriod').addEventListener('change', () => {
      syncFilterFields();
      load().catch((e) => toast(e.message));
    });
    $('#dashYear').addEventListener('change', () => load().catch((e) => toast(e.message)));
    $('#dashFrom').addEventListener('change', () => load().catch((e) => toast(e.message)));
    $('#dashTo').addEventListener('change', () => load().catch((e) => toast(e.message)));
    load().catch((e) => toast(e.message));
  });
})();
