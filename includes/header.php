<?php
/**
 * Shared page header
 * @var string $pageTitle
 * @var string $bodyClass
 * @var string $activeNav
 */
$pageTitle = $pageTitle ?? 'ILAAJ CRM';
$bodyClass = $bodyClass ?? '';
$activeNav = $activeNav ?? '';
$role = current_role();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — ILAAJ CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,400;7..72,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
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
<?php require ROOT_PATH . '/includes/navigation.php'; ?>
<main class="main-content">
<?php
$flash = flash_get();
if ($flash):
?>
<div class="alert alert-<?= e($flash['type']) ?>" data-flash><?= e($flash['message']) ?></div>
<?php endif; ?>
