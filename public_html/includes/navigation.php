<?php
$role = current_role();
$activeNav = $activeNav ?? '';

/**
 * Sidebar entries — icon-only vertical nav with tooltip on hover.
 * Icons use inline SVG so we don't add any dependency.
 */
$editorLinks = [
    [
        'key'   => 'dashboard',
        'label' => 'Dashboard',
        'href'  => base_url('pages/dashboard.php'),
        'icon'  => 'grid',
    ],
    [
        'key'   => 'patients',
        'label' => 'Patients',
        'href'  => base_url('pages/patients.php'),
        'icon'  => 'user',
    ],
    [
        'key'   => 'pending',
        'label' => 'Pending replies',
        'href'  => base_url('pages/pending.php'),
        'icon'  => 'inbox',
    ],
    [
        'key'   => 'meetings',
        'label' => 'Meetings',
        'href'  => base_url('pages/meetings.php'),
        'icon'  => 'calendar',
    ],
    [
        'key'   => 'import',
        'label' => 'Import Excel',
        'href'  => base_url('pages/import.php'),
        'icon'  => 'upload',
    ],
    [
        'key'   => 'drive',
        'label' => 'Drive Setup',
        'href'  => base_url('pages/drive_setup.php'),
        'icon'  => 'cloud',
    ],
];

$ameerLinks = [
    [
        'key'   => 'dashboard',
        'label' => 'Dashboard',
        'href'  => base_url('pages/dashboard.php'),
        'icon'  => 'grid',
    ],
    [
        'key'   => 'advisor',
        'label' => 'Patients',
        'href'  => base_url('pages/advisor.php'),
        'icon'  => 'user',
    ],
    [
        'key'   => 'pending',
        'label' => 'Pending replies',
        'href'  => base_url('pages/pending.php'),
        'icon'  => 'inbox',
    ],
];

$links = is_editor() ? $editorLinks : (is_ameer() ? $ameerLinks : []);

$pendingLeft = 0;
if ($links) {
    try {
        require_once ROOT_PATH . '/lib/PatientRepository.php';
        $pendingLeft = PatientRepository::pendingCount();
    } catch (Throwable $e) {
        $pendingLeft = 0;
    }
}

/**
 * Render one of the built-in inline icons.
 */
if (!function_exists('nav_icon')) {
function nav_icon(string $name): string
{
    $stroke = 'stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"';
    $icons = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5" ' . $stroke . '/>'
                . '<rect x="14" y="3" width="7" height="7" rx="1.5" ' . $stroke . '/>'
                . '<rect x="3" y="14" width="7" height="7" rx="1.5" ' . $stroke . '/>'
                . '<rect x="14" y="14" width="7" height="7" rx="1.5" ' . $stroke . '/>',
        'user' => '<circle cx="12" cy="8" r="4" ' . $stroke . '/>'
                . '<path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" ' . $stroke . '/>',
        'users' => '<circle cx="9" cy="8" r="3.5" ' . $stroke . '/>'
                . '<path d="M2.5 20c1-3.2 3.5-5 6.5-5s5.5 1.8 6.5 5" ' . $stroke . '/>'
                . '<circle cx="17" cy="9" r="2.8" ' . $stroke . '/>'
                . '<path d="M15 15c2 .3 4.5 1.8 5.5 4.5" ' . $stroke . '/>',
        'inbox' => '<path d="M3 13v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6" ' . $stroke . '/>'
                . '<path d="M3 13l2.5-8a2 2 0 0 1 1.9-1.4h9.2A2 2 0 0 1 18.5 5L21 13" ' . $stroke . '/>'
                . '<path d="M3 13h5l1.5 2.5h5L16 13h5" ' . $stroke . '/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2" ' . $stroke . '/>'
                . '<path d="M3.5 10h17M8 3v4M16 3v4" ' . $stroke . '/>',
        'upload' => '<path d="M12 4v11M7 9l5-5 5 5" ' . $stroke . '/>'
                . '<path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" ' . $stroke . '/>',
        'cloud' => '<path d="M7 18h10a4 4 0 0 0 .8-7.9 6 6 0 0 0-11.6 1A3.5 3.5 0 0 0 7 18z" ' . $stroke . '/>',
        'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3" ' . $stroke . '/>'
                . '<path d="M10 8l-4 4 4 4M6 12h11" ' . $stroke . '/>',
    ];
    return $icons[$name] ?? '';
}
}
?>
<button type="button" class="nav-toggle" id="navToggle" aria-label="Menu">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    <span>Menu</span>
</button>

<aside class="sidebar" id="appNav">
    <a class="sidebar-brand" href="<?= e(base_url('pages/dashboard.php')) ?>" title="ILAAJ CRM">
        <img src="<?= e(asset_url('images/logo.png')) ?>" alt="ILAAJ CRM">
    </a>

    <nav class="sidebar-nav" aria-label="Primary">
        <?php foreach ($links as $link): ?>
            <?php
            $isPending = $link['key'] === 'pending';
            $badgeLabel = ($isPending && $pendingLeft > 0) ? ($pendingLeft > 9 ? '9+' : (string) $pendingLeft) : '';
            $aria = $link['label'] . ($isPending && $pendingLeft > 0 ? ', ' . $pendingLeft . ' pending' : '');
            ?>
            <a href="<?= e($link['href']) ?>"
               class="sidebar-item <?= $activeNav === $link['key'] ? 'active' : '' ?>"
               data-tooltip="<?= e($link['label']) ?>"
               data-nav="<?= e($link['key']) ?>"
               aria-label="<?= e($aria) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><?= nav_icon($link['icon']) ?></svg>
                <?php if ($badgeLabel !== ''): ?>
                    <span class="nav-badge"><?= e($badgeLabel) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-spacer"></div>

    <?php if ($role): ?>
        <a href="<?= e(base_url('index.php?action=switch')) ?>"
           class="sidebar-item logout"
           data-tooltip="Switch role"
           aria-label="Switch role">
            <svg viewBox="0 0 24 24" aria-hidden="true"><?= nav_icon('logout') ?></svg>
        </a>
    <?php endif; ?>
</aside>
