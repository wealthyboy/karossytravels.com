<?php

namespace App\Travel\Exceptions;

use RuntimeException;
use Throwable;

final class CheckoutIdentityException extends RuntimeException
{
    public function __construct(
        public readonly string $publicMessage,
        string $internalMessage,
        public readonly ?string $field = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($internalMessage, 0, $previous);
    }
}
