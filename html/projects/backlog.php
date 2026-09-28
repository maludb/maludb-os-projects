<?php
declare(strict_types=1);
/** /projects/{KEY}/backlog — the project's backlog tab; slice 1 renders it through view.php until its slice fills it. */
$_GET['tab'] = 'backlog';
require __DIR__ . '/view.php';
