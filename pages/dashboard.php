<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_any_role();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$pageScripts = ['dashboard.js'];
require ROOT_PATH . '/includes/header.php';
$dashYear = (int) date('Y');
$dashToday = date('Y-m-d');
$dashYearStart = $dashYear . '-01-01';
?>
<div class="dash-period" id="dashFilters">
    <select id="dashPeriod" aria-label="Period">
        <option value="all" selected>All time</option>
        <option value="year">Year</option>
        <option value="custom">Custom</option>
    </select>
    <select id="dashYear" hidden aria-label="Year">
        <option value="<?= $dashYear ?>"><?= $dashYear ?></option>
    </select>
    <input type="date" id="dashFrom" hidden value="<?= e($dashYearStart) ?>" aria-label="From">
    <input type="date" id="dashTo" hidden value="<?= e($dashToday) ?>" aria-label="To">
</div>

<section>
    <div id="statsPatients" class="stat-grid">
        <div class="stat-card tone-mint"><div class="label">Loading…</div></div>
    </div>
</section>

<div class="section-label">Conversations &amp; response</div>
<div id="statsMessages" class="stat-grid"></div>

<div class="section-label">Operations</div>
<div id="statsOps" class="stat-grid"></div>

<div class="two-col" style="margin-top:0.5rem">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 style="margin:0">Patients by country</h2>
            </div>
        </div>
        <div id="chartCountry" class="chart-bars"></div>
    </div>
    <div class="card">
        <div class="card-head">
            <div>
                <h2 style="margin:0">Patients by city</h2>
            </div>
        </div>
        <div id="chartCity" class="chart-bars"></div>
    </div>
</div>

<div class="two-col" style="margin-top:1rem">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 style="margin:0">Patients by occupation</h2>
            </div>
        </div>
        <div id="chartOccupation" class="chart-bars"></div>
    </div>
    <div class="card">
        <div class="card-head">
            <div>
                <h2 style="margin:0">Recent meetings</h2>
            </div>
        </div>
        <div id="recentMeetings"></div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
