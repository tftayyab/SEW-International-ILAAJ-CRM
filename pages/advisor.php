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
    <div class="page-header">
        <div>
            <h2 style="margin:0;font-family:var(--font-read);font-size:1.15rem">Patient roster</h2>
            <p>Select a patient to read the conversation.</p>
        </div>
    </div>
    <div class="filter-bar">
        <div class="field field-grow"><label>Search</label><input type="search" id="advisorSearch" placeholder="Name, number, city…"></div>
        <div class="field"><label>Country</label><input type="text" id="advisorCountry"></div>
        <div class="field"><label>City</label><input type="text" id="advisorCity"></div>
        <div class="filter-actions">
            <span class="muted" style="font-size:0.85rem">Updates as you type</span>
        </div>
    </div>
    <div id="advisorCards" class="info-grid"></div>
    <div id="advisorPagination" class="pagination"></div>
</div>

<div id="advisorDetailView" hidden>
    <div class="page-header">
        <div>
            <button type="button" class="btn btn-secondary btn-sm" id="btnBackToList">← All patients</button>
            <h2 id="advName" style="margin-top:0.75rem;font-family:var(--font-read);font-size:1.35rem"></h2>
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
