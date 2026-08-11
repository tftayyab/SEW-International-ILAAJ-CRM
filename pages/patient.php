<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/PatientRepository.php';
require_once ROOT_PATH . '/lib/SystemState.php';

require_editor();

$id = (int) ($_GET['id'] ?? 0);
$patient = PatientRepository::find($id);
if (!$patient) {
    flash_set('error', 'Patient not found.');
    redirect(base_url('pages/patients.php'));
}

try {
    SystemState::setActivePatient($id);
} catch (Throwable $e) {
    log_error('set active patient', $e);
}

$pageTitle = $patient['name'];
$activeNav = 'patients';
$pageScripts = ['patient.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <p><a href="<?= e(base_url('pages/patients.php')) ?>">← Patients</a></p>
        <h1 id="patientName"><?= e($patient['name']) ?></h1>
    </div>
    <div class="actions">
        <button type="button" class="btn btn-ameer" id="btnSendToAmeer">Present to Ameer Sahab</button>
        <button type="button" class="btn btn-secondary" id="btnEditPatient">Edit details</button>
        <a class="btn btn-secondary" href="<?= e(base_url('pages/gallery.php?id=' . (int) $patient['id'])) ?>">Open gallery</a>
        <button type="button" class="btn btn-danger" id="btnDeletePatient">Delete</button>
    </div>
</div>

<div id="ameerSyncBanner" class="forced-banner show">
    Presented to Ameer Sahab. If he moves away, click <strong>Present to Ameer Sahab</strong> again.
</div>

<div class="patient-hero" id="patientHero" data-patient-id="<?= (int) $patient['id'] ?>">
    <?php if (!empty($patient['profile_image_id'])): ?>
        <img class="avatar-lg img-loading" data-image-id="<?= (int) $patient['profile_image_id'] ?>" alt="">
    <?php endif; ?>
    <div style="flex:1;min-width:220px">
        <div class="info-grid" id="patientInfo">
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
        <p class="profile-only-note" style="margin-top:0.85rem">
            Only the profile picture shows here. Open the <a href="<?= e(base_url('pages/gallery.php?id=' . (int) $patient['id'])) ?>">gallery</a> for all photos.
        </p>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="page-header" style="margin-bottom:0.75rem">
        <h2 style="margin:0;font-size:1.15rem">Conversation</h2>
        <button type="button" class="btn btn-sm" id="btnAddMessage">+ Add message</button>
    </div>
    <div id="conversation" class="conversation"></div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
