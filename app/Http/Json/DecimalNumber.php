<?php

declare(strict_types=1);

namespace App\Http\Json;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final readonly class DecimalNumber
{
    public function __construct(public string $literal)
    {
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $literal)) {
            throw new InvalidArgumentException('Invalid JSON decimal literal.');
        }
    }

    public static function money(string|int $value): self
    {
        return new self((string) BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp));
    }

    public static function rate(string|int $value): self
    {
        $decimal = BigDecimal::of($value);
        $scale = max(2, $decimal->getScale());

        return new self((string) $decimal->toScale($scale));
    }
}
