<?php
declare(strict_types=1);
/** / — the dashboard: my open issues, my projects, the active sprints. Reads the mcp_* views and the report functions only. */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/features/home/queries.php';
require_login();
$pdo = db();
log_screen_view($pdo, 'home');
$me = current_member();
$id = (int) $me['id'];
render_screen('Dashboard', view('home/page.php', [
    'me' => $me, 'issues' => home_my_issues($pdo, $id), 'projects' => home_my_projects($pdo, $id), 'sprints' => home_active_sprints($pdo), 'tz' => member_timezone(),
]), ['activeNav' => 'home', 'screen' => 'home']);
