<?php
declare(strict_types=1);
/** /sprints has no list of its own — sprints belong to a project. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
redirect('/projects');
