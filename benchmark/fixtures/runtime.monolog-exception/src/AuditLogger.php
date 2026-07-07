<?php

class AuditLogger
{
    public function __construct(private readonly SimpleLogger $logger)
    {
    }

    public function logUserDeletion(int $userId): void
    {
        // BUG: SimpleLogger has no `info()` method — only `log(level, message, context)`.
        // The call below throws an Error at runtime because the method doesn't exist.
        /* @phpstan-ignore-next-line */
        $this->logger->info("user $userId deleted");
    }
}
