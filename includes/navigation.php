<?php
$role = current_role();
$activeNav = $activeNav ?? '';
?>
<header class="app-header">
    <div class="header-inner">
        <a class="brand" href="<?= e(base_url(is_ameer() ? 'pages/advisor.php' : 'pages/dashboard.php')) ?>">
            <span class="brand-mark">ILAAJ</span>
            <span class="brand-sub"><?= is_ameer() ? 'Advisor' : 'Editor' ?></span>
        </a>

        <button type="button" class="nav-toggle" id="navToggle" aria-label="Menu">☰</button>

        <nav class="app-nav" id="appNav">
            <?php if (is_editor()): ?>
                <a href="<?= e(base_url('pages/dashboard.php')) ?>" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="<?= e(base_url('pages/patients.php')) ?>" class="<?= $activeNav === 'patients' ? 'active' : '' ?>">Patients</a>
                <a href="<?= e(base_url('pages/workers.php')) ?>" class="<?= $activeNav === 'workers' ? 'active' : '' ?>">Workers</a>
                <a href="<?= e(base_url('pages/meetings.php')) ?>" class="<?= $activeNav === 'meetings' ? 'active' : '' ?>">Meetings</a>
                <a href="<?= e(base_url('pages/import.php')) ?>" class="<?= $activeNav === 'import' ? 'active' : '' ?>">Import Excel</a>
                <a href="<?= e(base_url('pages/drive_setup.php')) ?>" class="<?= $activeNav === 'drive' ? 'active' : '' ?>">Drive Setup</a>
            <?php elseif (is_ameer()): ?>
                <a href="<?= e(base_url('pages/advisor.php')) ?>" class="<?= $activeNav === 'advisor' ? 'active' : '' ?>">Patients</a>
            <?php endif; ?>
            <a class="nav-switch" href="<?= e(base_url('index.php?action=logout')) ?>">Switch Role</a>
        </nav>
    </div>
</header>
