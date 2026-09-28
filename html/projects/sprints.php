<?php
declare(strict_types=1);
/** /projects/{KEY}/sprints — the project's sprints tab; slice 1 renders it through view.php until its slice fills it. */
$_GET['tab'] = 'sprints';
require __DIR__ . '/view.php';
