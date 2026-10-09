<?php
// php -S router for the proofs (scripts/prove-picker.sh): the same rewrites as deploy/apache-projects.conf, so the canonical
// URLs (/projects/PKA/edit, /issues/PKA-1, /activity) answer under `php -S` as they do under Apache. Not used in production.
$root = __DIR__ . '/../html';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$qs = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '';
$go = static function (string $script, string $extra = '') use ($root, $qs): bool {
    $_SERVER['SCRIPT_NAME'] = $script;
    $_SERVER['SCRIPT_FILENAME'] = $root . $script;
    $q = trim($extra . '&' . $qs, '&');
    parse_str($q, $_GET);
    chdir(dirname($root . $script));
    require $root . $script;
    return true;
};
if (is_file($root . $uri) && !str_ends_with($uri, '.php')) { return false; }          // static assets
if (is_file($root . $uri)) { return $go($uri); }
if (is_dir($root . $uri) && is_file($root . rtrim($uri, '/') . '/index.php')) { return $go(rtrim($uri, '/') . '/index.php'); }
$m = [];
$rules = [
    '#^/sso$#' => '/sso.php',
    '#^/projects/new$#' => '/projects/form.php',
    '#^/projects/([A-Z][A-Z0-9]{1,9})/edit$#' => '/projects/form.php?key=$1',
    '#^/projects/([A-Z][A-Z0-9]{1,9})/(backlog|board|sprints|epics|issues|reports|settings)$#' => '/projects/$2.php?key=$1',
    '#^/projects/([A-Z][A-Z0-9]{1,9})$#' => '/projects/view.php?key=$1',
    '#^/issues/new$#' => '/issues/form.php',
    '#^/issues/([A-Z][A-Z0-9]{1,9}-[0-9]+)/edit$#' => '/issues/form.php?key=$1',
    '#^/issues/([A-Z][A-Z0-9]{1,9}-[0-9]+)$#' => '/issues/view.php?key=$1',
    '#^/sprints/([0-9]+)$#' => '/sprints/view.php?id=$1',
    '#^/epics/([0-9]+)$#' => '/epics/view.php?id=$1',
    '#^/my-work$#' => '/my-work.php',
    '#^/activity$#' => '/activity.php',
];
foreach ($rules as $re => $to) {
    if (preg_match($re, $uri, $m)) {
        $to = preg_replace_callback('/\$(\d)/', static fn($x) => $m[(int) $x[1]], $to);
        [$script, $extra] = array_pad(explode('?', $to, 2), 2, '');
        return $go($script, $extra);
    }
}
if (preg_match('#^/(.+)/new$#', $uri, $m) && is_file("$root/$m[1]/form.php")) { return $go("/$m[1]/form.php"); }
if (preg_match('#^/(.+)/([0-9]+)/edit$#', $uri, $m) && is_file("$root/$m[1]/form.php")) { return $go("/$m[1]/form.php", 'id=' . $m[2]); }
if (preg_match('#^/(.+)/([0-9]+)$#', $uri, $m) && is_file("$root/$m[1]/view.php")) { return $go("/$m[1]/view.php", 'id=' . $m[2]); }
if (preg_match('#^/(.+?)/?$#', $uri, $m) && is_file("$root/$m[1].php")) { return $go("/$m[1].php"); }
http_response_code(404);
echo 'Not found';
