<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (input('action') === 'logout') {
    auth_clear();
    redirect(base_url('pages/login.php'));
}

require_login();

if (input('action') === 'switch') {
    clear_role();
    redirect(base_url('index.php'));
}

$role = input('role');
if ($role === ROLE_EDITOR || $role === ROLE_AMEER) {
    redirect(base_url('pages/dashboard.php?view=' . $role));
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ILAAJ CRM — Patient Advisor System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
    <link rel="icon" href="<?= e(asset_url('images/logo.png')) ?>" type="image/png">
</head>
<body class="landing-body">
    <div class="landing">
        <div class="landing-logo">
            <img src="<?= e(asset_url('images/logo.png')) ?>" alt="Silsila Warisi">
        </div>
        <header class="landing-header">
            <p class="landing-eyebrow">SEW International &middot; <?= e(date('j M Y')) ?></p>
        </header>

        <div class="landing-actions">
            <a class="role-card role-ameer" href="<?= e(base_url('index.php?role=ameer')) ?>">
                <span class="role-label">Ameer Sahab</span>
                <span class="role-desc">Read-only advisor view for patient conversations</span>
            </a>
            <a class="role-card role-editor" href="<?= e(base_url('index.php?role=editor')) ?>">
                <span class="role-label">Editor</span>
                <span class="role-desc">Full management dashboard, patients, meetings &amp; import</span>
            </a>
        </div>

        <footer class="landing-footer">
            <p>Signed in as <strong><?= e((string) current_username()) ?></strong>
                &middot; <a href="<?= e(base_url('index.php?action=logout')) ?>">Sign out</a></p>
        </footer>
    </div>
</body>
</html>
