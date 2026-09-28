<?php
declare(strict_types=1);
/** Action `project_archive` — arrives with slice 6 (settings). Registered, not built. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_post();
verify_csrf();
refuse(501, 'Archiving comes with slice 6.');
