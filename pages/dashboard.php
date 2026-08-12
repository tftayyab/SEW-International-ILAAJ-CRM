<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_editor();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$pageScripts = ['dashboard.js'];
require ROOT_PATH . '/includes/header.php';
?>
<section>
    <div class="page-header" style="margin-bottom:0.75rem">
        <div>
            <h2 style="margin:0">Group overview</h2>
            <p style="margin:0.25rem 0 0;color:var(--ink-muted);font-size:0.95rem">Live patient, conversation, and activity metrics for your team.</p>
        </div>
    </div>
    <div id="statsPatients" class="stat-grid">
        <div class="stat-card tone-mint"><div class="label">Loading…</div></div>
    </div>
</section>

<div class="section-label">Conversations &amp; response</div>
<div id="statsMessages" class="stat-grid"></div>

<div class="section-label">Operations &amp; media</div>
<div id="statsOps" class="stat-grid"></div>

<div class="two-col" style="margin-top:0.5rem">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 style="margin:0">Patients by country</h2>
                <p class="card-sub">Top locations across the roster.</p>
            </div>
        </div>
        <div id="chartCountry" class="chart-bars"></div>
    </div>
    <div class="card">
        <div class="card-head">
            <div>
                <h2 style="margin:0">Patients by city</h2>
                <p class="card-sub">Where your patients are based.</p>
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
                <p class="card-sub">Roles most represented.</p>
            </div>
        </div>
        <div id="chartOccupation" class="chart-bars"></div>
    </div>
    <div class="card">
        <div class="card-head">
            <div>
                <h2 style="margin:0">Recent meetings</h2>
                <p class="card-sub">Latest gatherings on the calendar.</p>
            </div>
        </div>
        <div id="recentMeetings"></div>
    </div>
</div>

<div class="two-col" style="margin-top:1rem">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 style="margin:0">Recent patients</h2>
                <p class="card-sub">Newest entries in the ledger.</p>
            </div>
        </div>
        <div id="recentPatients"></div>
    </div>
    <div class="card">
        <div class="card-head">
            <div>
                <h2 style="margin:0">Recent conversations</h2>
                <p class="card-sub">Latest messages exchanged.</p>
            </div>
        </div>
        <div id="recentMessages"></div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
