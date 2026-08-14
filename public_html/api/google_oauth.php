<?php
/**
 * Google OAuth callback — exchanges code for refresh token.
 * Used once during Drive setup.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_PATH . '/lib/GoogleDriveService.php';

require_editor();

$error = null;
$refreshToken = null;
$alreadyHadRefresh = false;

try {
    if (!empty($_GET['error'])) {
        throw new RuntimeException('Google denied access: ' . (string) $_GET['error']);
    }

    $state = (string) ($_GET['state'] ?? '');
    $expected = (string) ($_SESSION['google_oauth_state'] ?? '');
    if ($state === '' || $expected === '' || !hash_equals($expected, $state)) {
        throw new RuntimeException('Invalid OAuth state. Start again from Drive Setup.');
    }
    unset($_SESSION['google_oauth_state']);

    $code = trim((string) ($_GET['code'] ?? ''));
    if ($code === '') {
        throw new RuntimeException('Missing authorization code.');
    }

    $tokens = GoogleDriveService::exchangeCode($code);
    $refreshToken = $tokens['refresh_token'] ?? null;
    if (!$refreshToken) {
        $alreadyHadRefresh = true;
        throw new RuntimeException(
            'Google did not return a refresh token. Revoke app access at https://myaccount.google.com/permissions then connect again (prompt=consent is required).'
        );
    }

    $_SESSION['google_refresh_token_pending'] = $refreshToken;
} catch (Throwable $e) {
    $error = $e->getMessage();
    log_error('google oauth callback', $e);
}

$pageTitle = 'Google Drive Connected';
$activeNav = 'drive';
require ROOT_PATH . '/includes/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <p><a class="btn" href="<?= e(base_url('pages/drive_setup.php')) ?>">Back to Drive Setup</a></p>
<?php else: ?>
    <div class="alert alert-success">Connected. Copy the refresh token into your <code>.env</code> file.</div>
    <div class="card">
        <label>GOOGLE_REFRESH_TOKEN</label>
        <textarea id="refreshTokenBox" readonly rows="4" style="font-family:monospace"><?= e((string) $refreshToken) ?></textarea>
        <div class="actions" style="margin-top:1rem">
            <button type="button" class="btn" id="copyToken">Copy token</button>
            <a class="btn btn-secondary" href="<?= e(base_url('pages/drive_setup.php')) ?>">Back to Drive Setup</a>
        </div>
        <p class="muted" style="margin-top:1rem">
            Add this line to <code>.env</code> on this machine and on your server:
        </p>
        <pre style="background:var(--bg-elevated);padding:0.85rem;border-radius:8px;overflow:auto">GOOGLE_REFRESH_TOKEN=<?= e((string) $refreshToken) ?></pre>
    </div>
    <script>
      document.getElementById('copyToken').addEventListener('click', async () => {
        const text = document.getElementById('refreshTokenBox').value;
        try {
          await navigator.clipboard.writeText(text);
          AppUtil.toast('Copied.');
        } catch (e) {
          document.getElementById('refreshTokenBox').select();
          AppUtil.toast('Select and copy manually (Ctrl+C).');
        }
      });
    </script>
<?php endif; ?>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
