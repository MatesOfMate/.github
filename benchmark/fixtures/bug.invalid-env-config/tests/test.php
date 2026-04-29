<?php

require __DIR__.'/../src/Config.php';

Config::load(__DIR__.'/../.env');

$expected = ['DB_HOST' => 'localhost', 'DB_PORT' => '5432'];
foreach ($expected as $key => $value) {
    $actual = Config::get($key);
    if ($value !== $actual) {
        fwrite(\STDERR, "Config::get('$key') expected '$value', got '$actual'\n");
        exit(1);
    }
}

echo "ok\n";
exit(0);
