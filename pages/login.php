<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (input('action') === 'logout') {
    auth_clear();
    redirect(base_url('pages/login.php'));
}

if (is_logged_in()) {
    redirect(base_url(current_role() ? 'pages/dashboard.php' : 'index.php'));
}

$error = '';
if (request_method() === 'POST') {
    if (!verify_csrf()) {
        $error = 'Please refresh the page and try again.';
    } else {
        $user = attempt_login((string) input('username', ''), (string) input('password', ''));
        if ($user) {
            redirect(base_url('index.php'));
        }
        $error = 'Invalid username or password.';
    }
}

$flash = flash_get();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — ILAAJ CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
    <link rel="icon" href="<?= e(asset_url('images/logo.png')) ?>" type="image/png">
</head>
<body class="landing-body">
    <div class="landing landing--narrow">
        <div class="landing-logo">
            <img src="<?= e(asset_url('images/logo.png')) ?>" alt="Silsila Warisi">
        </div>
        <header class="landing-header">
            <p class="landing-eyebrow">SEW International &middot; <?= e(date('j M Y')) ?></p>
            <h1 class="landing-brand">ILAAJ CRM</h1>
            <p class="landing-sub">Sign in to continue</p>
        </header>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="card login-card" method="post" action="<?= e(base_url('pages/login.php')) ?>" autocomplete="on">
            <?= csrf_field() ?>
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username" value="<?= e((string) input('username', '')) ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn login-submit">Sign in</button>
        </form>
    </div>
</body>
</html>
