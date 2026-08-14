<?php
/**
 * Shared page header — sidebar shell + brand/date top bar.
 * @var string $pageTitle
 * @var string $bodyClass
 * @var string $activeNav
 */
$pageTitle = $pageTitle ?? 'ILAAJ CRM';
$showPageHeading = $showPageHeading ?? true;
$bodyClass = $bodyClass ?? '';
$activeNav = $activeNav ?? '';
$role = current_role();

$brandLabel = 'SEW International';
$roleLabel = is_ameer() ? 'Advisor View' : (is_editor() ? 'Editor Console' : '');
$today = date('j M Y');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — ILAAJ CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
    <link rel="icon" href="<?= e(asset_url('images/logo.png')) ?>" type="image/png">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <script>
        window.APP = {
            baseUrl: <?= json_encode(base_url()) ?>,
            apiUrl: <?= json_encode(base_url('api')) ?>,
            csrfToken: <?= json_encode(csrf_token()) ?>,
            role: <?= json_encode($role) ?>,
            isEditor: <?= json_encode(is_editor()) ?>,
            isAmeer: <?= json_encode(is_ameer()) ?>
        };
    </script>
</head>
<body class="<?= e($bodyClass) ?> role-<?= e((string) $role) ?>">
<div class="app-shell">
<?php require ROOT_PATH . '/includes/navigation.php'; ?>
<main class="main-wrap">
    <header class="app-topbar">
        <div class="title-block">
            <p class="eyebrow"><?= e($brandLabel) ?><?= $roleLabel ? ' &middot; ' . e($roleLabel) : '' ?></p>
            <?php if ($showPageHeading): ?>
            <h1><?= e($pageTitle) ?></h1>
            <?php endif; ?>
        </div>
        <div class="date-block">
            <p class="today-label">Today</p>
            <p class="today-date"><?= e($today) ?></p>
        </div>
    </header>
<?php
$flash = flash_get();
if ($flash):
?>
<div class="alert alert-<?= e($flash['type']) ?>" data-flash><?= e($flash['message']) ?></div>
<?php endif; ?>
