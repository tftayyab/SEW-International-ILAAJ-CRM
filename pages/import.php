<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_editor();

$pageTitle = 'Import Excel';
$activeNav = 'import';
$pageScripts = ['import.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="card" id="uploadCard">
    <h2 style="margin-top:0">Upload file</h2>
    <p class="muted">Accepted formats: .xlsx, .xls, .csv.<br>
        Expected: <code>Date</code> (date of the <strong>last</strong> message only), patient fields, <code>Details of Concern</code> (first patient message), then alternating <code>Ameer Sahab Response</code> / <code>Followup Remarks</code> (patient). Earlier message dates are unknown (shown as —). New messages you add later can have dates.</p>
    <form id="importForm">
        <div class="field" style="margin:1rem 0">
            <label for="importFile">Excel / CSV file</label>
            <input type="file" id="importFile" name="file" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
        </div>
        <button type="submit" class="btn" id="btnPreview">Preview Import</button>
    </form>
</div>

<div id="previewCard" class="card" hidden style="margin-top:1rem">
    <h2 style="margin-top:0">Import preview</h2>
    <div id="previewSummary" class="preview-summary"></div>
    <div id="previewErrors"></div>
    <div id="resolutionSection"></div>
    <div class="actions" style="margin-top:1rem">
        <button type="button" class="btn" id="btnConfirmImport">Confirm Import</button>
        <button type="button" class="btn btn-secondary" id="btnCancelImport">Cancel</button>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <h2 style="margin-top:0">Import history</h2>
    <div id="importHistory"></div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
