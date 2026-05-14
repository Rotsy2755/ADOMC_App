<?php

declare(strict_types=1);

namespace App\Message;

/** Async email notification when a long computation finishes. */
final class SendReportEmailMessage
{
    public function __construct(
        public readonly int $userId,
        public readonly int $problemId,
        public readonly string $subject,
        public readonly string $body,
    ) {
    }
}
