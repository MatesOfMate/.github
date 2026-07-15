<?php

class AuditLogger
{
    public function __construct(private readonly SimpleLogger $logger)
    {
    }

    public function logUserDeletion(int $userId): void
    {
        $this->logger->info("user $userId deleted");
    }
}
