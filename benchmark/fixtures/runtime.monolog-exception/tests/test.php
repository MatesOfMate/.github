<?php

require __DIR__.'/../src/SimpleLogger.php';
require __DIR__.'/../src/AuditLogger.php';

$logger = new SimpleLogger();
$audit = new AuditLogger($logger);

try {
    $audit->logUserDeletion(42);
} catch (\Throwable $exception) {
    fwrite(\STDERR, 'AuditLogger threw at runtime: '.$exception->getMessage()."\n");
    exit(1);
}

if (1 !== \count($logger->records)) {
    fwrite(\STDERR, 'Expected exactly one audit record.'."\n");
    exit(1);
}

$record = $logger->records[0];
if ('audit' !== $record['level'] || !\is_string($record['message']) || !str_contains($record['message'], '42')) {
    fwrite(\STDERR, 'Audit record was not formed correctly: '.var_export($record, true)."\n");
    exit(1);
}

echo "ok\n";
exit(0);
