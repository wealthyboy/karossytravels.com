<?php

namespace App\Travel\Exceptions;

use RuntimeException;
use Throwable;

final class BookingCreationException extends RuntimeException
{
    public function __construct(
        public readonly string $stage,
        public readonly string $publicMessage,
        string $internalMessage,
        public readonly ?string $providerLocator = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($internalMessage, 0, $previous);
    }
}
