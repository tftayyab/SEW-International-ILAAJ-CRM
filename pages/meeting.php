<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/MeetingRepository.php';
require_editor();

$id = (int) ($_GET['id'] ?? 0);
$meeting = MeetingRepository::find($id);
if (!$meeting) {
    flash_set('error', 'Meeting not found.');
    redirect(base_url('pages/meetings.php'));
}

$pageTitle = $meeting['name'];
$activeNav = 'meetings';
$pageScripts = ['meeting.js'];
require ROOT_PATH . '/includes/header.php';

$patientsAttended = count(array_filter($meeting['patients'] ?? [], static fn($p) => !empty($p['attended'])));
$patientsTotal = count($meeting['patients'] ?? []);
?>
<div class="page-header">
    <div>
        <p><a href="<?= e(base_url('pages/meetings.php')) ?>">← Meetings</a></p>
        <h2 style="margin:0"><?= e($meeting['name']) ?></h2>
        <p class="muted">Mark who was present. Expected attendees are set when creating/editing the meeting.</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="<?= e(base_url('pages/meeting_form.php?id=' . (int)$meeting['id'])) ?>">Edit</a>
    </div>
</div>

<div class="card">
    <h2 style="margin-top:0">Meeting Information</h2>
    <div class="info-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.75rem">
        <div><strong>Date</strong><div><?= e(format_date($meeting['meeting_date']) ?: '—') ?></div></div>
        <div><strong>Start</strong><div><?= e($meeting['start_time'] ?: '—') ?></div></div>
        <div><strong>End</strong><div><?= e($meeting['end_time'] ?: '—') ?></div></div>
        <div><strong>Location</strong><div><?= e($meeting['location'] ?: '—') ?></div></div>
        <div><strong>Patients present</strong><div id="patientsStat"><?= (int)$patientsAttended ?> / <?= (int)$patientsTotal ?></div></div>
    </div>
    <?php if ($meeting['description']): ?>
        <p style="margin-top:1rem"><strong>Description</strong><br><?= nl2br(e($meeting['description'])) ?></p>
    <?php endif; ?>
    <?php if ($meeting['notes']): ?>
        <p style="margin-top:1rem"><strong>Notes</strong><br><?= nl2br(e($meeting['notes'])) ?></p>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:1rem" id="attendanceRoot" data-meeting-id="<?= (int)$meeting['id'] ?>">
    <h2 style="margin-top:0">Patients</h2>
    <div class="attendance-controls" data-scope="patients">
        <input type="search" class="att-search" id="patientsSearch" placeholder="Search patients…" autocomplete="off">
        <div class="att-status-filters" role="group" aria-label="Patient status filter">
            <button type="button" class="att-filter active" data-status="all">All</button>
            <button type="button" class="att-filter" data-status="present">Present</button>
            <button type="button" class="att-filter" data-status="absent">Absent</button>
        </div>
        <div class="att-bulk-actions">
            <button type="button" class="btn btn-sm" id="patientsMarkPresent">Mark all present</button>
            <button type="button" class="btn btn-sm btn-secondary" id="patientsMarkAbsent">Mark all absent</button>
        </div>
    </div>
    <p class="muted att-filter-hint" id="patientsFilterHint"></p>
    <div id="patientsAttendance" class="attendance-list"></div>
</div>

<script>
  window.MEETING_VIEW = <?= json_encode([
      'id' => (int) $meeting['id'],
      'patients' => array_map(static fn($p) => [
          'id' => (int) $p['id'],
          'name' => $p['name'],
          'number' => $p['number'] ?? '',
          'city' => $p['city'] ?? '',
          'attended' => (int) ($p['attended'] ?? 0),
      ], $meeting['patients'] ?? []),
  ], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
