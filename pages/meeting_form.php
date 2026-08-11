<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/MeetingRepository.php';

require_editor();

$editId = (int) ($_GET['id'] ?? 0);
$meeting = null;
if ($editId > 0) {
    $meeting = MeetingRepository::find($editId);
    if (!$meeting) {
        flash_set('error', 'Meeting not found.');
        redirect(base_url('pages/meetings.php'));
    }
}

$pageTitle = $meeting ? 'Edit meeting' : 'Create meeting';
$activeNav = 'meetings';
$pageScripts = ['meeting_form.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <p><a href="<?= e(base_url('pages/meetings.php')) ?>">← Meetings</a></p>
        <h1><?= $meeting ? 'Edit meeting' : 'Create meeting' ?></h1>
        <p>Choose who is <strong>expected</strong> to attend. After saving, open the meeting to mark who was actually present.</p>
    </div>
</div>

<form id="meetingPageForm" class="card">
    <input type="hidden" name="id" id="meetingId" value="<?= (int) ($meeting['id'] ?? 0) ?>">
    <div class="form-grid">
        <div class="field full"><label>Meeting name *</label>
            <input type="text" name="name" id="meetingName" required value="<?= e($meeting['name'] ?? '') ?>">
        </div>
        <div class="field"><label>Date</label>
            <input type="date" name="meeting_date" value="<?= e($meeting['meeting_date'] ?? '') ?>">
        </div>
        <div class="field"><label>Location</label>
            <input type="text" name="location" value="<?= e($meeting['location'] ?? '') ?>">
        </div>
        <div class="field"><label>Start time</label>
            <input type="time" name="start_time" value="<?= e(isset($meeting['start_time']) ? substr((string)$meeting['start_time'], 0, 5) : '') ?>">
        </div>
        <div class="field"><label>End time</label>
            <input type="time" name="end_time" value="<?= e(isset($meeting['end_time']) ? substr((string)$meeting['end_time'], 0, 5) : '') ?>">
        </div>
        <div class="field full"><label>Description</label>
            <textarea name="description"><?= e($meeting['description'] ?? '') ?></textarea>
        </div>
        <div class="field full"><label>Notes</label>
            <textarea name="notes"><?= e($meeting['notes'] ?? '') ?></textarea>
        </div>
    </div>
</form>

<div class="panel-split" style="margin-top:1rem">
    <div class="card">
        <div class="page-header" style="margin-bottom:0.5rem">
            <h2 style="margin:0;font-size:1.1rem">Expected workers</h2>
            <div class="actions">
                <button type="button" class="btn btn-sm btn-secondary" id="btnPickWorkers">Select</button>
                <button type="button" class="btn btn-sm" id="btnAddWorkerModal">+ New worker</button>
            </div>
        </div>
        <div id="selectedWorkers" class="attendee-summary"></div>
        <p class="muted" id="workersEmpty">No workers selected yet.</p>
    </div>
    <div class="card">
        <div class="page-header" style="margin-bottom:0.5rem">
            <h2 style="margin:0;font-size:1.1rem">Expected patients</h2>
            <div class="actions">
                <button type="button" class="btn btn-sm btn-secondary" id="btnPickPatients">Select</button>
                <button type="button" class="btn btn-sm" id="btnAddPatientModal">+ New patient</button>
            </div>
        </div>
        <div id="selectedPatients" class="attendee-summary"></div>
        <p class="muted" id="patientsEmpty">No patients selected yet.</p>
    </div>
</div>

<div class="actions" style="margin-top:1.25rem">
    <button type="button" class="btn" id="btnSaveMeeting">Save meeting</button>
    <a class="btn btn-secondary" href="<?= e(base_url('pages/meetings.php')) ?>">Cancel</a>
</div>

<script>
  window.MEETING_INITIAL = <?= json_encode([
      'workers' => array_map(static fn($w) => [
          'id' => (int) $w['id'],
          'name' => $w['name'],
          'phone' => $w['phone'] ?? '',
      ], $meeting['workers'] ?? []),
      'patients' => array_map(static fn($p) => [
          'id' => (int) $p['id'],
          'name' => $p['name'],
          'number' => $p['number'] ?? '',
          'city' => $p['city'] ?? '',
      ], $meeting['patients'] ?? []),
  ], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
