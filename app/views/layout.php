<?php
/**
 * The app shell (design-system nxl skeleton). Wraps a screen's page HTML.
 * Data: title, content, activeNav?, screen?, entity?, recordId?
 * Load-bearing: nxl-* classes, #mobile-collapse, #menu-mini-button, asset order,
 * theme-customizer-init.min.js last. #page-content is the HTMX swap target.
 * Projects: SortableJS after htmx (the owner's answer 2); bindSortables() on load and after every swap.
 */
$title     = $title     ?? app_name();
$content   = $content   ?? '';
$activeNav = $activeNav ?? '';
$screen    = $screen    ?? '';
$entity    = $entity    ?? '';
$recordId  = $recordId  ?? '';
$m         = current_member();
$admin     = is_projects_admin();
$roleBadge = '';
if ($m !== null) {
    $st = db()->prepare('SELECT projects_roles FROM mcp_members WHERE member_id = :id');
    $st->execute(['id' => (int) $m['id']]);
    $roles = (string) ($st->fetchColumn() ?: '');
    $first = trim(explode(',', trim($roles, '{}'))[0] ?? '', '"');
    $roleBadge = $first !== '' ? $first : match ((string) ($m['business_role'] ?? 'user')) {
        'super_admin' => 'Super-admin', 'dept_admin' => 'Dept-admin', default => ($admin ? 'Projects Admin' : ''),
    };
}
$initials  = strtoupper(mb_substr((string) ($m['display_name'] ?? '?'), 0, 1));

$groups = [
    'Home' => [
        ['home', '/', 'feather-home', 'Dashboard', true],
        ['my-work', '/my-work', 'feather-check-square', 'My work', true],
    ],
    'Projects' => [
        ['projects-list', '/projects/', 'feather-trello', 'Projects', true],
    ],
    'Settings' => [
        ['settings', '/settings/', 'feather-settings', 'Settings', $admin],
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
    <!-- htmx 2 swaps nothing above 3xx by default; a refused write (422) re-renders its form or lands in #flash (HX-Retarget). -->    <meta name="htmx-config" content='{"responseHandling":[{"code":"204","swap":false},{"code":"[23]..","swap":true},{"code":"422","swap":true,"error":false},{"code":"[45]..","swap":false,"error":true}]}'>
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
                            <a href="/my-work" class="dropdown-item" hx-get="/my-work" hx-target="#page-content" hx-push-url="/my-work" id="header-me-link">
                                <i class="feather-check-square"></i><span>My work</span>
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
    <?php /* The record picker (app/picker.php, assets/js/record-picker.js): the ONE modal that chooses a record for any picker field on the
             screen — outside #page-content so swaps keep it. The second exemption to the no-modal rule, beside hx-confirm (design-system). */ ?>
    <div class="modal fade" id="record-picker" tabindex="-1" aria-labelledby="record-picker-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-md-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="record-picker-title">Choose</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="record-picker-close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="record-picker-search p-3 border-bottom">
                        <div class="input-group">
                            <span class="input-group-text"><i class="feather-search"></i></span>
                            <input type="search" class="form-control" id="record-picker-search" placeholder="Search…" autocomplete="off" aria-controls="record-picker-results"
                                   hx-get="/pick/" hx-trigger="input changed delay:300ms, search, pickerload" hx-target="#record-picker-results" hx-swap="innerHTML"
                                   hx-sync="this:replace" hx-vals="js:{...recordPickerValues()}" />
                        </div>
                    </div>
                    <div id="record-picker-results" aria-live="polite"></div>
                </div>
                <div class="modal-footer d-none" id="record-picker-footer">
                    <button type="button" class="btn btn-light-brand" id="record-picker-create-btn"><i class="feather-plus me-1"></i>New</button>
                </div>
            </div>
        </div>
    </div>

    <?= view('shared/assistant-bar.php') ?>

    <script src="/assets/vendors/js/vendors.min.js"></script>
    <script src="/assets/vendors/js/select2.min.js"></script>
    <script src="/assets/vendors/js/select2-active.min.js"></script>
    <script src="/assets/vendors/js/htmx.min.js"></script>
    <script src="/assets/vendors/sortablejs/Sortable.min.js"></script>
    <script>
        // Drag-and-drop is an enhancement over the buttons (docs/build-specs/sso-shell.md): a list or a
        // column with data-sortable posts the same action the buttons post — the moved item's data-id,
        // its neighbours as after/before, the destination's data-sortable-value as value.
        function bindSortables() {
            if (!window.Sortable) return;
            document.querySelectorAll('[data-sortable]').forEach(function (el) {
                if (el.__sortable) return;
                el.__sortable = Sortable.create(el, {
                    group: el.dataset.sortableGroup || undefined,
                    handle: el.dataset.sortableHandle || undefined,
                    animation: 120,
                    onEnd: function (evt) {
                        var to = evt.to, item = evt.item;
                        var url = to.dataset.sortableAction || el.dataset.sortableAction;
                        if (!url) return;
                        var prev = item.previousElementSibling, next = item.nextElementSibling;
                        var values = { id: item.dataset.id || '' };
                        if (prev && prev.dataset.id) values.after = prev.dataset.id;
                        if (next && next.dataset.id) values.before = next.dataset.id;
                        if (to.dataset.sortableValue !== undefined) values.value = to.dataset.sortableValue;
                        htmx.ajax('POST', url, { target: to.dataset.sortableTarget || el.dataset.sortableTarget || '#page-content', swap: 'innerHTML', values: values });
                    }
                });
            });
        }
        document.addEventListener('DOMContentLoaded', bindSortables);
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
            bindSortables();
        });
        // A refused request (4xx) is retargeted to #flash by the server; a network failure says so.
        document.body.addEventListener('htmx:responseError', function (e) {
            var f = document.getElementById('flash');
            if (f && e.detail.xhr && e.detail.xhr.status >= 500) { f.innerHTML = '<div class="alert alert-danger m-3">Something went wrong. Try again.</div>'; }
        });
    </script>
    <script src="/assets/js/common-init.min.js"></script>
    <script src="/assets/js/record-picker.js?v=20261009a"></script>
    <script src="/assets/js/theme-customizer-init.min.js"></script>
</body>
</html>
