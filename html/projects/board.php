<?php
declare(strict_types=1);
/** /projects/{KEY}/board — the project's board tab; slice 1 renders it through view.php until its slice fills it. */
$_GET['tab'] = 'board';
require __DIR__ . '/view.php';
