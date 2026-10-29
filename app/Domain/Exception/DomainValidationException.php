<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class DomainValidationException extends RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(public readonly array $errors, string $message = 'The given data was invalid.')
    {
        parent::__construct($message);
    }
}
