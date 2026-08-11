<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/PatientRepository.php';

require_any_role();

$id = (int) ($_GET['id'] ?? 0);
$patient = PatientRepository::find($id);
if (!$patient) {
    flash_set('error', 'Patient not found.');
    redirect(base_url(is_ameer() ? 'pages/advisor.php' : 'pages/patients.php'));
}

$backUrl = is_ameer()
    ? base_url('pages/advisor.php?patient=' . $id)
    : base_url('pages/patient.php?id=' . $id);

$pageTitle = 'Gallery — ' . $patient['name'];
$activeNav = is_ameer() ? 'advisor' : 'patients';
$pageScripts = ['gallery.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <p><a href="<?= e($backUrl) ?>">← Back to patient</a></p>
        <h1>Gallery</h1>
        <p class="muted"><?= e($patient['name']) ?> · <?= e($patient['number']) ?></p>
    </div>
    <?php if (is_editor()): ?>
        <button type="button" class="btn" id="btnUploadGallery">+ Upload photo</button>
    <?php endif; ?>
</div>

<div id="driveStatus" class="alert alert-warning" hidden></div>
<div id="galleryRoot" data-patient-id="<?= (int) $patient['id'] ?>" data-can-edit="<?= is_editor() ? '1' : '0' ?>">
    <div class="empty-state">Loading gallery…</div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
