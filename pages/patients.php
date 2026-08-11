<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_editor();

$pageTitle = 'Patients';
$activeNav = 'patients';
$pageScripts = ['patients.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <h1>Patients</h1>
        <p>Search and filter update as you type.</p>
    </div>
    <button type="button" class="btn" id="btnAddPatient">+ Add patient</button>
</div>

<div class="filter-bar" id="patientFilters">
    <div class="field field-grow"><label>Search</label><input type="search" name="q" placeholder="Name, number, city…"></div>
    <div class="field"><label>Number</label><input type="text" name="number" placeholder="Phone"></div>
    <div class="field"><label>Country</label><input type="text" name="country"></div>
    <div class="field"><label>City</label><input type="text" name="city"></div>
    <div class="field"><label>Occupation</label><input type="text" name="occupation"></div>
    <div class="field"><label>Mother</label><input type="text" name="mother_name"></div>
    <div class="filter-actions">
        <button type="button" class="btn btn-secondary" id="btnReset">Clear</button>
    </div>
</div>

<div id="patientsTable"></div>
<div id="patientsPagination" class="pagination"></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
