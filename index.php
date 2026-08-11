<?php
require_once __DIR__ . '/includes/bootstrap.php';

$role = input('role');
if ($role === ROLE_EDITOR || $role === ROLE_AMEER) {
    set_role($role);
    if ($role === ROLE_EDITOR) {
        redirect(base_url('pages/dashboard.php'));
    }
    redirect(base_url('pages/advisor.php'));
}

if (input('action') === 'logout') {
    clear_role();
    redirect(base_url('index.php'));
}

// Already has role? Optional redirect
if (current_role() === ROLE_EDITOR && !isset($_GET['stay'])) {
    // Stay on landing if they want to switch — show landing always when visiting index
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ILAAJ CRM — Patient Advisor System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,400;7..72,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body class="landing-body">
    <div class="landing">
        <header class="landing-header">
            <p class="landing-eyebrow">SEW International</p>
            <h1 class="landing-brand">ILAAJ CRM</h1>
            <p class="landing-sub">Patient Advisor &amp; Writer Management System</p>
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
            <p>Select a role to continue. No login required.</p>
        </footer>
    </div>
</body>
</html>
