<?php

declare(strict_types=1);

namespace IfCastle\RestApi\Pipeline;

use Psr\Log\AbstractLogger;

/**
 * Keeps every log record in memory so a test can read what the pipeline reported.
 */
final class CollectingLogger extends AbstractLogger
{
    /**
     * @var list<string>
     */
    public array $records           = [];

    #[\Override]
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[]            = $level . ': ' . $message;
    }
}
