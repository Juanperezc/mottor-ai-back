<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Money\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[Test]
    /** A20 */
    public function it_rounds_half_up_and_never_exposes_a_float(): void
    {
        self::assertSame('0.13', Money::of('0.125')->toString());
        self::assertSame('-0.13', Money::of('-0.125')->toString());
        self::assertSame('10.01', Money::of('10.005')->toString());
        self::assertSame('11.11', Money::of('10.01')->plus(Money::of('1.10'))->toString());
        self::assertSame('2.50', Money::of('10.00')->percentage('25')->toString());
    }
}
