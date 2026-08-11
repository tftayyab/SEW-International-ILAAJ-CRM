<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_editor();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$pageScripts = ['dashboard.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p>Overview of patients, conversations, meetings and imports.</p>
    </div>
</div>

<div id="dashStats" class="stat-grid">
    <div class="stat-card"><div class="label">Loading…</div></div>
</div>

<div class="two-col">
    <div class="card">
        <h2>Patients by country</h2>
        <div id="chartCountry" class="chart-bars"></div>
    </div>
    <div class="card">
        <h2>Patients by city</h2>
        <div id="chartCity" class="chart-bars"></div>
    </div>
</div>

<div class="two-col" style="margin-top:1rem">
    <div class="card">
        <h2>Patients by occupation</h2>
        <div id="chartOccupation" class="chart-bars"></div>
    </div>
    <div class="card">
        <h2>Recent meetings</h2>
        <div id="recentMeetings"></div>
    </div>
</div>

<div class="two-col" style="margin-top:1rem">
    <div class="card">
        <h2>Recent patients</h2>
        <div id="recentPatients"></div>
    </div>
    <div class="card">
        <h2>Recent conversations</h2>
        <div id="recentMessages"></div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
