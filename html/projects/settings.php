<?php
declare(strict_types=1);
/** /projects/{KEY}/settings — the project's settings tab; slice 1 renders it through view.php until its slice fills it. */
$_GET['tab'] = 'settings';
require __DIR__ . '/view.php';
