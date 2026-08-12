<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_any_role();

$pageTitle = 'Pending replies';
$activeNav = 'pending';
$pageScripts = ['pending.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <h2 style="margin:0"><?= is_ameer() ? 'Waiting for your reply' : "Awaiting Ameer Sahab's response" ?></h2>
        <p><?= is_ameer() ? 'Patients whose last message has not been answered yet.' : "Patients whose last message hasn't been answered yet." ?></p>
    </div>
    <div class="actions">
        <span class="pill pill-warn" id="pendingCount">0 pending</span>
    </div>
</div>

<div class="toolbar">
    <input type="search" id="pendingSearch" placeholder="<?= is_ameer() ? 'Search name or mother…' : 'Search name, mother, or number…' ?>" autocomplete="off">
</div>

<div id="pendingCards" class="info-grid">
    <div class="empty-state">Loading…</div>
</div>
<div id="pendingPagination" class="pagination"></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
