<?php
declare(strict_types=1);
/** /projects/{KEY}/epics — the project's epics tab; slice 1 renders it through view.php until its slice fills it. */
$_GET['tab'] = 'epics';
require __DIR__ . '/view.php';
