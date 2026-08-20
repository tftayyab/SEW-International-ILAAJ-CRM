<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_ameer();

$pageTitle = 'Patients';
$activeNav = 'advisor';
$bodyClass = 'advisor-page';
$pageScripts = ['advisor.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div id="forcedBanner" class="forced-banner">Editor selected a patient — opening now…</div>

<div id="advisorListView">
    <div class="filter-bar">
        <div class="field field-grow"><label>Search</label><input type="search" id="advisorSearch" placeholder="Name, mother, city, country…"></div>
    </div>
    <div id="advisorCards" class="info-grid"></div>
    <div id="advisorPagination" class="pagination"></div>
</div>

<div id="advisorDetailView" hidden>
    <div class="record-nav-bar">
        <button type="button" class="btn btn-secondary btn-sm record-nav-bar__btn" id="btnPrevPatient" disabled aria-label="Previous patient">←</button>
        <button type="button" class="btn btn-secondary btn-sm record-nav-bar__btn" id="btnNextPatient" disabled aria-label="Next patient">→</button>
    </div>
    <div class="page-header">
        <div>
            <button type="button" class="btn btn-secondary btn-sm" id="btnBackToList">← All patients</button>
        </div>
        <a class="btn btn-secondary" id="btnAdvGallery" hidden href="#">Open gallery</a>
    </div>

    <div class="patient-hero" id="advHero"></div>

    <div class="card" style="margin-top:1rem">
        <h2 style="margin-top:0;font-size:1.2rem">Conversation</h2>
        <div id="advConversation" class="conversation"></div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
