<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_editor();

$pageTitle = 'Meetings';
$activeNav = 'meetings';
$pageScripts = ['meetings.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="toolbar">
    <input type="search" id="meetingSearch" placeholder="Name, location, description, or meeting link…" autocomplete="off">
    <button type="button" class="btn btn-secondary" id="btnMeetingSearch">Search</button>
    <button type="button" class="btn" id="btnAddMeeting">+ Create Meeting</button>
</div>

<div id="meetingsTable"></div>
<div id="meetingsPagination" class="pagination"></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
