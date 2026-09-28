<?php
/**
 * The app shell (design-system nxl skeleton). Wraps a screen's page HTML.
 * Data: title, content, activeNav?, screen?, entity?, recordId?
 * Load-bearing: nxl-* classes, #mobile-collapse, #menu-mini-button, asset order,
 * theme-customizer-init.min.js last. #page-content is the HTMX swap target.
 */
$title     = $title     ?? app_name();
$content   = $content   ?? '';
$activeNav = $activeNav ?? '';
$screen    = $screen    ?? '';
$entity    = $entity    ?? '';
$recordId  = $recordId  ?? '';
$m         = current_member();
$staff     = hr_is_staff();
$roleBadge = match ((string) ($m['business_role'] ?? 'user')) {
    'super_admin' => 'Super-admin', 'dept_admin' => 'Dept-admin', default => ($staff ? 'HR staff' : ''),
};
if ($staff && ($m['business_role'] ?? '') !== 'super_admin') { $roleBadge = 'HR staff'; }
$initials  = strtoupper(mb_substr((string) ($m['display_name'] ?? '?'), 0, 1));

$groups = [
    'Home' => [
        ['dashboard', '/', 'feather-home', 'Dashboard', true],
    ],
    'People' => [
        ['people-list', '/people/', 'feather-users', 'People', true],
        ['departments-list', '/departments/', 'feather-grid', 'Departments', true],
    ],
    'Leave' => [
        ['leave-requests', '/leave/', 'feather-sun', 'Requests', true],
        ['leave-calendar', '/leave/calendar', 'feather-calendar', 'Calendar', true],
        ['leave-balances', '/leave/balances/', 'feather-pie-chart', 'Balances', true],
    ],
    'Pay' => [
        ['pay-runs', '/payruns/', 'feather-dollar-sign', 'Pay runs', can_run_pay()],
        ['payslip', '/payruns/mine', 'feather-file-text', 'My payslips', true],
    ],
    'Reviews' => [
        ['reviews-list', '/reviews/', 'feather-star', 'Reviews', true],
    ],
    'Settings' => [
        ['settings', '/settings/', 'feather-settings', 'Settings', $staff],
        ['leave-types', '/settings/leave-types/', 'feather-list', 'Leave types', $staff],
        ['holidays', '/settings/holidays/', 'feather-flag', 'Holidays', $staff],
        ['tokens', '/settings/tokens/', 'feather-key', 'Tokens', true],
        ['activity', '/activity', 'feather-activity', 'Activity', true],
    ],
];
$navlink = function (string $id, string $url, string $icon, string $label) use ($activeNav): string {
    $active = $activeNav === $id ? ' active' : '';
    return '<li class="nxl-item" id="nav-' . e($id) . '">'
        . '<a class="nxl-link' . $active . '" href="' . e($url) . '"'
        . ' hx-get="' . e($url) . '" hx-target="#page-content" hx-swap="innerHTML"'
        . ' hx-push-url="' . e($url) . '">'
        . '<span class="nxl-micon"><i class="' . e($icon) . '"></i></span>'
        . '<span class="nxl-mtext">' . e($label) . '</span></a></li>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="<?= e(app_name()) ?>" />
    <meta name="csrf-token" id="csrf-token-meta" content="<?= e(csrf_token()) ?>" />
    <title><?= e($title) ?> · <?= e(app_name()) ?></title>
    <link rel="shortcut icon" type="image/png" href="/assets/images/favicon.png" />
    <link rel="stylesheet" type="text/css" href="/assets/css/bootstrap.min.css" />
    <link rel="stylesheet" type="text/css" href="/assets/vendors/css/vendors.min.css" />
    <link rel="stylesheet" type="text/css" href="/assets/vendors/css/select2.min.css" />
    <link rel="stylesheet" type="text/css" href="/assets/css/theme.min.css" />
    <link rel="stylesheet" type="text/css" href="/assets/css/app-overrides.css" />
</head>
<body>
    <!--! [Start] Navigation !-->
    <nav class="nxl-navigation" id="left-sidenav">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="/" class="b-brand" hx-get="/" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="/">
                    <img src="/assets/images/logo-full.png" alt="<?= e(app_name()) ?>" class="logo logo-lg" />
                    <img src="/assets/images/logo-abbr.png" alt="" class="logo logo-sm" />
                </a>
            </div>
            <div class="navbar-content">
                <ul class="nxl-navbar">
                    <?php foreach ($groups as $groupLabel => $items): ?>
                        <?php $shown = array_filter($items, static fn (array $i): bool => $i[4]); if ($shown === []) { continue; } ?>
                        <li class="nxl-item nxl-caption"><label><?= e($groupLabel) ?></label></li>
                        <?php foreach ($shown as $item) { echo $navlink($item[0], $item[1], $item[2], $item[3]); } ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </nav>
    <!--! [End] Navigation !-->

    <!--! [Start] Header !-->
    <header class="nxl-header" id="top-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box"><div class="hamburger-inner"></div></div>
                    </div>
                </a>
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button"><i class="feather-align-left"></i></a>
                    <a href="javascript:void(0);" id="menu-expend-button" style="display: none"><i class="feather-arrow-right"></i></a>
                </div>
            </div>
            <div class="header-right ms-auto">
                <div class="d-flex align-items-center">
                    <div class="nxl-h-item d-none d-sm-flex">
                        <a href="<?= e(launcher_url()) ?>" class="nxl-head-link me-0" data-bs-toggle="tooltip" title="All applications" id="header-launcher-link">
                            <i class="feather-grid"></i>
                        </a>
                    </div>
                    <div class="nxl-h-item dark-light-theme">
                        <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button"><i class="feather-moon"></i></a>
                        <a href="javascript:void(0);" class="nxl-head-link me-0 light-button" style="display:none"><i class="feather-sun"></i></a>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside" id="header-user-menu">
                            <span class="avatar-text avatar-md user-avtar me-0"><?= e($initials) ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <span class="avatar-text avatar-md user-avtar"><?= e($initials) ?></span>
                                    <div>
                                        <h6 class="text-dark mb-0"><?= e($m['display_name'] ?? 'Member') ?>
                                            <?php if ($roleBadge !== ''): ?><span class="badge bg-soft-primary text-primary ms-1"><?= e($roleBadge) ?></span><?php endif; ?>
                                        </h6>
                                        <span class="fs-12 fw-medium text-muted"><?= e($m['email'] ?? '') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="/people/<?= (int) ($m['id'] ?? 0) ?>" class="dropdown-item" hx-get="/people/<?= (int) ($m['id'] ?? 0) ?>" hx-target="#page-content" hx-push-url="/people/<?= (int) ($m['id'] ?? 0) ?>" id="header-me-link">
                                <i class="feather-user"></i><span>My record</span>
                            </a>
                            <a href="<?= e(launcher_url()) ?>" class="dropdown-item" id="header-launcher-item">
                                <i class="feather-grid"></i><span>All applications</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <form method="post" action="/logout.php" class="px-2">
                                <?= csrf_field() ?>
                                <button type="submit" id="header-logout-btn" class="dropdown-item border-0 bg-transparent w-100 text-start">
                                    <i class="feather-log-out"></i><span>Sign out</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!--! [End] Header !-->

    <!--! [Start] Main Content !-->
    <main class="nxl-container app-has-assistant-bar">
        <div id="flash"></div>
        <div class="nxl-content" id="page-content"
             data-screen="<?= e($screen) ?>" data-entity="<?= e($entity) ?>" data-record-id="<?= e($recordId) ?>">
            <?= $content ?>
        </div>
        <footer class="footer" id="page-footer">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>© <?= date('Y') ?> <?= e(app_name()) ?> · an application for the Business OS</span>
            </p>
            <div class="d-flex align-items-center gap-4">
                <a href="/activity" class="fs-11 fw-semibold text-uppercase" hx-get="/activity" hx-target="#page-content" hx-push-url="/activity">Activity</a>
                <a href="<?= e(launcher_url()) ?>" class="fs-11 fw-semibold text-uppercase">Applications</a>
            </div>
        </footer>
    </main>
    <!--! [End] Main Content !-->

    <?= view('shared/assistant-bar.php') ?>

    <script src="/assets/vendors/js/vendors.min.js"></script>
    <script src="/assets/vendors/js/select2.min.js"></script>
    <script src="/assets/vendors/js/select2-active.min.js"></script>
    <script src="/assets/vendors/js/htmx.min.js"></script>
    <script>
        document.body.addEventListener('htmx:configRequest', function (e) {
            if (e.detail.verb && e.detail.verb.toLowerCase() !== 'get') {
                var meta = document.querySelector('#csrf-token-meta');
                if (meta) { e.detail.headers['X-CSRF-Token'] = meta.content; }
            }
        });
        // A swapped screen: title, sidebar highlight, theme widgets that bind on ready.
        document.body.addEventListener('htmx:afterSwap', function (e) {
            var pc = document.getElementById('page-content');
            if (!pc) return;
            var xt = e.detail && e.detail.xhr ? e.detail.xhr.getResponseHeader('HX-Title') : null;
            if (xt) { document.title = decodeURIComponent(xt); }
            var screen = pc.dataset.screen || '';
            document.querySelectorAll('.nxl-navbar .nxl-link').forEach(function (a) { a.classList.remove('active'); });
            var link = document.querySelector('#nav-' + screen + ' .nxl-link');
            if (link) { link.classList.add('active'); }
            if (window.jQuery) {
                if (jQuery.fn.tooltip) { jQuery('[data-bs-toggle="tooltip"]').tooltip(); }
                if (jQuery.fn.select2) { jQuery('#page-content select[data-select2-selector]').each(function () { if (!jQuery(this).hasClass('select2-hidden-accessible')) { jQuery(this).select2({ width: '100%' }); } }); }
            }
            var nav = document.querySelector('nav.nxl-navigation');
            if (nav && nav.classList.contains('mob-navigation-active')) { nav.classList.remove('mob-navigation-active'); var ov = document.querySelector('.nxl-menu-overlay'); if (ov) ov.remove(); }
        });
        // A refused request (4xx) is retargeted to #flash by the server; a network failure says so.
        document.body.addEventListener('htmx:responseError', function (e) {
            var f = document.getElementById('flash');
            if (f && e.detail.xhr && e.detail.xhr.status >= 500) { f.innerHTML = '<div class="alert alert-danger m-3">Something went wrong. Try again.</div>'; }
        });
    </script>
    <script src="/assets/js/common-init.min.js"></script>
    <script src="/assets/js/theme-customizer-init.min.js"></script>
</body>
</html>
