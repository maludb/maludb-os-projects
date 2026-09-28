<?php
declare(strict_types=1);
/** /projects/{KEY}/reports — the project's reports tab; slice 1 renders it through view.php until its slice fills it. */
$_GET['tab'] = 'reports';
require __DIR__ . '/view.php';
