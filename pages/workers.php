<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_editor();

$pageTitle = 'Workers';
$activeNav = 'workers';
$pageScripts = ['workers.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <h1>Workers</h1>
        <p>People who can be associated with meetings.</p>
    </div>
    <button type="button" class="btn" id="btnAddWorker">+ Add Worker</button>
</div>

<div class="toolbar">
    <input type="search" id="workerSearch" placeholder="Search workers…" style="max-width:280px">
    <button type="button" class="btn btn-secondary" id="btnWorkerSearch">Search</button>
</div>

<div id="workersTable"></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
