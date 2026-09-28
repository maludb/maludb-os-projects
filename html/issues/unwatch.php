<?php
declare(strict_types=1);
/** Action `unwatch` at the manifest's path — watch.php does both, told by action=unwatch. */
$_POST['action'] = 'unwatch';
require __DIR__ . '/watch.php';
