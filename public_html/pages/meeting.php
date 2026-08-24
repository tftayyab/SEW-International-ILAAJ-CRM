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

$pageTitle = 'Meeting';
$showPageHeading = false;
$activeNav = 'meetings';
$pageScripts = ['meeting.js'];
require ROOT_PATH . '/includes/header.php';

$patientsAttended = count(array_filter($meeting['patients'] ?? [], static fn($p) => !empty($p['attended'])));
$patientsTotal = count($meeting['patients'] ?? []);

$backParams = [];
if ((int) ($_GET['page'] ?? 0) > 1) {
    $backParams['page'] = (int) $_GET['page'];
}
if (!empty($_GET['q'])) {
    $backParams['q'] = (string) $_GET['q'];
}
$backQs = $backParams ? '?' . http_build_query($backParams) : '';
$backUrl = with_view(base_url('pages/meetings.php' . $backQs));
?>
<div class="page-header">
    <div>
        <p><a href="<?= e($backUrl) ?>">← Meetings</a></p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="<?= e(base_url('pages/meeting_form.php?id=' . (int)$meeting['id'])) ?>">Edit</a>
    </div>
</div>

<div class="card">
    <h2 style="margin-top:0">Meeting Information</h2>
    <div class="info-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.75rem">
        <div><strong>Meeting</strong><div><?= e($meeting['name']) ?></div></div>
        <div><strong>Date</strong><div><?= e(format_date($meeting['meeting_date']) ?: '—') ?></div></div>
        <div><strong>Start</strong><div><?= e($meeting['start_time'] ?: '—') ?></div></div>
        <div><strong>End</strong><div><?= e($meeting['end_time'] ?: '—') ?></div></div>
        <div><strong>Location</strong><div><?= e($meeting['location'] ?: '—') ?></div></div>
        <div><strong>Patients present</strong><div id="patientsStat"><?= (int)$patientsAttended ?> / <?= (int)$patientsTotal ?></div></div>
    </div>
    <?php if ($meeting['description']): ?>
        <p style="margin-top:1rem"><strong>Description</strong><br><?= nl2br(e($meeting['description'])) ?></p>
    <?php endif; ?>
    <?php if (!empty($meeting['meeting_link'])): ?>
        <?php
            $rawLink = (string) $meeting['meeting_link'];
            $href = preg_match('#^https?://#i', $rawLink) ? $rawLink : 'https://' . $rawLink;
        ?>
        <div class="copy-link-row">
            <strong>Meeting link</strong>
            <a href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer"><?= e($rawLink) ?></a>
            <button type="button" class="icon-btn" data-copy="<?= e($rawLink) ?>" title="Copy link">Copy</button>
        </div>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:1rem" id="attendanceRoot" data-meeting-id="<?= (int)$meeting['id'] ?>">
    <h2 style="margin-top:0">Patients</h2>
    <div class="attendance-controls" data-scope="patients">
        <input type="search" class="att-search" id="patientsSearch" placeholder="Name, number, or city…" autocomplete="off">
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
