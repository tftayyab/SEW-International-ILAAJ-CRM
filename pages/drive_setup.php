<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/GoogleDriveService.php';

require_editor();

if (isset($_GET['connect'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;
    try {
        redirect(GoogleDriveService::authorizationUrl($state));
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
        redirect(base_url('pages/drive_setup.php'));
    }
}

$status = GoogleDriveService::status();
$redirectUri = $status['redirect_uri'];

$pageTitle = 'Google Drive Setup';
$activeNav = 'drive';
require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Google Drive Setup</h1>
        <p>OAuth + refresh token — everything stored in <code>.env</code> for local and deploy.</p>
    </div>
</div>

<div class="card">
    <h2 style="margin-top:0">Status</h2>
    <ul>
        <li>Client ID: <?= $status['has_client_id'] ? '✓ set' : '✗ missing in .env' ?></li>
        <li>Client secret: <?= $status['has_client_secret'] ? '✓ set' : '✗ missing in .env' ?></li>
        <li>Refresh token: <?= $status['has_refresh_token'] ? '✓ set' : '✗ not connected yet' ?></li>
        <li>Folder ID: <?= $status['has_folder_id'] ? '✓ set' : '✗ missing in .env' ?></li>
        <li>Ready to upload: <?= $status['configured'] ? '<strong>✓ yes</strong>' : '<strong>not yet</strong>' ?></li>
    </ul>
</div>

<div class="card" style="margin-top:1rem">
    <h2 style="margin-top:0">1. Create OAuth credentials in Google Cloud</h2>
    <ol>
        <li>Open <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a></li>
        <li>Enable <strong>Google Drive API</strong></li>
        <li>APIs &amp; Services → <strong>Credentials</strong> → Create credentials → <strong>OAuth client ID</strong></li>
        <li>Application type: <strong>Web application</strong></li>
        <li>Add this Authorized redirect URI (exact match):</li>
    </ol>
    <pre style="background:var(--bg-elevated);padding:0.85rem;border-radius:8px;overflow:auto"><?= e($redirectUri) ?></pre>
    <p class="muted">On deploy, set <code>GOOGLE_OAUTH_REDIRECT_URI</code> in <code>.env</code> to your live callback URL, and add that same URI in Google Cloud.</p>
</div>

<div class="card" style="margin-top:1rem">
    <h2 style="margin-top:0">2. Put Client ID / Secret / Folder in <code>.env</code></h2>
    <pre style="background:var(--bg-elevated);padding:0.85rem;border-radius:8px;overflow:auto">GOOGLE_CLIENT_ID=....apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-...
GOOGLE_DRIVE_FOLDER_ID=1e3tcePyJYdC2fzu_-8AStACoLj4wpPqi
GOOGLE_DRIVE_MAKE_PUBLIC=true</pre>
    <p class="muted">Folder ID only (or full folder URL — both work). Create a Drive folder owned by your Google account; uploads go there.</p>
</div>

<div class="card" style="margin-top:1rem">
    <h2 style="margin-top:0">3. Connect once (get refresh token)</h2>
    <p>This opens Google consent, then shows a refresh token to paste into <code>.env</code>.</p>
    <?php if ($status['has_client_id'] && $status['has_client_secret']): ?>
        <a class="btn btn-ameer" href="<?= e(base_url('pages/drive_setup.php?connect=1')) ?>">Connect Google Drive</a>
    <?php else: ?>
        <div class="alert alert-warning">Add <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> to <code>.env</code>, then refresh this page.</div>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:1rem">
    <h2 style="margin-top:0">Deploy</h2>
    <p>Copy the same <code>.env</code> values to the server (client id, secret, refresh token, folder id, redirect URI). No JSON key file needed.</p>
</div>

<p class="muted" style="margin-top:1rem">Also see <code>docs/GOOGLE_DRIVE_SETUP.md</code> in the project folder.</p>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
