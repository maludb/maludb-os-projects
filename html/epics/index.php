<?php
declare(strict_types=1);
/** /epics/?project=KEY — the epics live on the project's tab; this only sends there. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
$key = strtoupper(request_string('project'));
redirect(preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) ? '/projects/' . $key . '/epics' : '/projects');
