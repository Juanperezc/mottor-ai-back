<?php

declare(strict_types=1);

namespace App\Domain\Rules;

use Brick\Math\BigDecimal;

final class RuleLabelFormatter
{
    public static function number(string|int $value): string
    {
        $normalized = (string) BigDecimal::of($value);
        $normalized = str_contains($normalized, '.') ? rtrim(rtrim($normalized, '0'), '.') : $normalized;
        [$integer, $decimal] = array_pad(explode('.', $normalized, 2), 2, null);
        $sign = str_starts_with($integer, '-') ? '-' : '';
        $integer = ltrim($integer, '-');
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $integer) ?? $integer;

        return $sign.$integer.($decimal === null ? '' : ','.$decimal);
    }
}
