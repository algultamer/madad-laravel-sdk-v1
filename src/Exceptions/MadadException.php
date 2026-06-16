<?php

namespace Madad\Sdk\Exceptions;

use RuntimeException;

class MadadException extends RuntimeException
{
    /**
     * @param  int  $status  HTTP status code from the Madad API.
     * @param  string|null  $errorCode  Business error code (e.g. CONFLICT, NOT_FOUND).
     * @param  array<string, mixed>|null  $body  The decoded response body.
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $errorCode = null,
        public readonly ?array $body = null,
    ) {
        parent::__construct($message, $status);
    }

    public function isConflict(): bool
    {
        return $this->status === 409;
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }
}
