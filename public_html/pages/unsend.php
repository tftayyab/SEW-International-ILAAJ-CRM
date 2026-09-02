<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_editor();

$pageTitle = 'Unsend response';
$activeNav = 'unsend';
$pageScripts = ['unsend.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="toolbar">
    <input type="search" id="unsendSearch" placeholder="Name or number…" autocomplete="off">
    <button type="button" class="btn btn-secondary" id="btnUnsendSearch">Search</button>
</div>

<div id="unsendTable"></div>
<div id="unsendPagination" class="pagination"></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
