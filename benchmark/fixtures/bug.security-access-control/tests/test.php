<?php

require __DIR__.'/../src/User.php';
require __DIR__.'/../src/Resource.php';
require __DIR__.'/../src/AccessControl.php';

$ac = new AccessControl();
$owner = new User(id: 1);
$other = new User(id: 2);
$admin = new User(id: 3, roles: ['admin']);
$resource = new Resource(id: 100, ownerId: 1);

$cases = [
    [$owner, $resource, true,  'owner can access'],
    [$admin, $resource, true,  'admin can access'],
    [$other, $resource, false, 'non-owner non-admin must be rejected'],
];

foreach ($cases as [$user, $res, $expected, $label]) {
    $actual = $ac->canAccess($user, $res);
    if ($expected !== $actual) {
        fwrite(\STDERR, "$label — expected ".var_export($expected, true).', got '.var_export($actual, true)."\n");
        exit(1);
    }
}

echo "ok\n";
exit(0);
