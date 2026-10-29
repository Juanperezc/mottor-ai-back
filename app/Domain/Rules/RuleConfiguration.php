<?php

declare(strict_types=1);

namespace App\Domain\Rules;

use App\Domain\Exception\DomainValidationException;
use Brick\Math\BigDecimal;

final class RuleConfiguration
{
    /** @param array<string, mixed> $configuration */
    public static function string(array $configuration, string $key): string
    {
        $value = $configuration[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw self::invalid($key);
        }

        return trim($value);
    }

    /** @param array<string, mixed> $configuration */
    public static function integer(array $configuration, string $key, int $minimum = 0): int
    {
        $value = $configuration[$key] ?? null;
        if (! is_int($value) || $value < $minimum) {
            throw self::invalid($key);
        }

        return $value;
    }

    /** @param array<string, mixed> $configuration */
    public static function decimal(array $configuration, string $key): string
    {
        $value = $configuration[$key] ?? null;
        if (! is_string($value) || ! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw self::invalid($key);
        }

        return $value;
    }

    /** @param array<string, mixed> $configuration */
    public static function percent(array $configuration, string $key): string
    {
        $value = self::decimal($configuration, $key);
        $decimal = BigDecimal::of($value);

        if ($decimal->isNegative() || $decimal->isGreaterThan(100)) {
            throw self::invalid($key);
        }

        return $value;
    }

    private static function invalid(string $key): DomainValidationException
    {
        return new DomainValidationException(["rules.{$key}" => ["Invalid rule field '{$key}'."]]);
    }
}
