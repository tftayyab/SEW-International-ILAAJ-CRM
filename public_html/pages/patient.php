<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/PatientRepository.php';

require_editor();

$id = (int) ($_GET['id'] ?? 0);
$patient = PatientRepository::find($id);
if (!$patient) {
    flash_set('error', 'Patient not found.');
    redirect(base_url('pages/patients.php'));
}

$navFrom = ($_GET['from'] ?? '') === 'pending' ? 'pending' : (($_GET['from'] ?? '') === 'unsend' ? 'unsend' : 'patients');
$backParams = [];
if ((int) ($_GET['page'] ?? 0) > 1) {
    $backParams['page'] = (int) $_GET['page'];
}
if (!empty($_GET['q'])) {
    $backParams['q'] = (string) $_GET['q'];
}
if ($navFrom === 'patients') {
    if (!empty($_GET['sort']) && $_GET['sort'] !== 'last_activity') {
        $backParams['sort'] = (string) $_GET['sort'];
    }
    if (!empty($_GET['dir']) && strtoupper((string) $_GET['dir']) !== 'DESC') {
        $backParams['dir'] = (string) $_GET['dir'];
    }
}
$backBase = match ($navFrom) {
    'pending' => 'pages/pending.php',
    'unsend' => 'pages/unsend.php',
    default => 'pages/patients.php',
};
$backQs = $backParams ? '?' . http_build_query($backParams) : '';
$backUrl = with_view(base_url($backBase . $backQs));
$backLabel = match ($navFrom) {
    'pending' => '← Pending replies',
    'unsend' => '← Unsend response',
    default => '← Patients',
};

$pageTitle = 'Patient';
$showPageHeading = false;
$activeNav = match ($navFrom) {
    'pending' => 'pending',
    'unsend' => 'unsend',
    default => 'patients',
};
$pageScripts = ['patient.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="record-nav-bar">
    <button type="button" class="btn btn-secondary btn-sm record-nav-bar__btn" id="btnPrevPatient" disabled aria-label="Previous patient">←</button>
    <button type="button" class="btn btn-secondary btn-sm record-nav-bar__btn" id="btnNextPatient" disabled aria-label="Next patient">→</button>
</div>
<div class="page-header">
    <div>
        <p><a id="patientBackLink" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a></p>
    </div>
    <div class="actions">
        <button type="button" class="btn btn-ameer" id="btnSendToAmeer">Present to Ameer Sahab</button>
        <button type="button" class="btn btn-secondary" id="btnEditPatient">Edit details</button>
        <a class="btn btn-secondary" href="<?= e(with_view(base_url('pages/gallery.php?id=' . (int) $patient['id']))) ?>">Open gallery</a>
        <button type="button" class="btn btn-danger" id="btnDeletePatient">Delete</button>
    </div>
</div>

<div class="patient-hero" id="patientHero" data-patient-id="<?= (int) $patient['id'] ?>" data-response-sent="<?= !empty($patient['response_sent']) ? '1' : '0' ?>">
    <?php if (!empty($patient['profile_image_id'])): ?>
        <img class="avatar-lg img-loading" data-image-id="<?= (int) $patient['profile_image_id'] ?>" alt="">
    <?php endif; ?>
    <div style="flex:1;min-width:220px">
        <div class="info-grid" id="patientInfo">
            <div><strong>Patient</strong><span id="patientName"><?= e($patient['name']) ?></span></div>
            <div><strong>Mother</strong><span><?= e($patient['mother_name'] ?: '—') ?></span></div>
            <div><strong>Number</strong><span><?= e($patient['number']) ?></span></div>
            <div><strong>Country</strong><span><?= e($patient['country'] ?: '—') ?></span></div>
            <div><strong>City</strong><span><?= e($patient['city'] ?: '—') ?></span></div>
            <div><strong>Occupation</strong><span><?= e($patient['occupation'] ?: '—') ?></span></div>
        </div>
        <div class="notes-box" id="patientNotes" <?= $patient['notes'] ? '' : 'hidden' ?>>
            <strong>Notes</strong>
            <div style="margin-top:0.35rem;white-space:pre-wrap"><?= e($patient['notes'] ?? '') ?></div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="page-header" style="margin-bottom:0.75rem">
        <h2 style="margin:0">Conversation</h2>
        <button type="button" class="btn btn-sm" id="btnAddMessage">+ Add message</button>
    </div>
    <div id="conversation" class="conversation"></div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
