<?php
declare(strict_types=1);
require_once __DIR__ . '/apps.php';

/** Pure check: is $role one of $appKey's stakeholder roles? */
function role_allowed_in_app(string $role, string $appKey): bool {
    $app = wrd_app($appKey);
    return $app ? in_array($role, $app['roles'], true) : false;
}
