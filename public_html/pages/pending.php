<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_any_role();

$pageTitle = 'Pending replies';
$activeNav = 'pending';
$pageScripts = ['pending.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="toolbar">
    <input type="search" id="pendingSearch" placeholder="<?= is_ameer() ? 'Name or mother…' : 'Name, mother, or number…' ?>" autocomplete="off">
</div>

<div id="pendingCards" class="info-grid">
    <div class="empty-state">Loading…</div>
</div>
<div id="pendingPagination" class="pagination"></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
