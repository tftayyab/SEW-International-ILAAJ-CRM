<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_editor();

$pageTitle = 'Patients';
$activeNav = 'patients';
$pageScripts = ['patients.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="filter-bar" id="patientFilters">
    <div class="field field-grow"><label>Search</label><input type="search" name="q" placeholder="Name, mother, number, city, country, occupation…"></div>
    <div class="filter-actions">
        <button type="button" class="btn btn-secondary" id="btnReset">Clear</button>
        <button type="button" class="btn" id="btnAddPatient">+ Add patient</button>
    </div>
</div>

<div id="patientsTable"></div>
<div id="patientsPagination" class="pagination"></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
