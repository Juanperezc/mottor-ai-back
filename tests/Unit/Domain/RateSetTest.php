<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Exception\DomainValidationException;
use App\Domain\Money\RateSet;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RateSetTest extends TestCase
{
    #[Test]
    /** A21 */
    public function it_uses_a_direct_rate(): void
    {
        self::assertSame('1450.00', (string) (new RateSet(['USD_ARS' => '1450.00']))->factor('USD', 'ARS'));
    }

    #[Test]
    /** A22 */
    public function it_uses_a_twelve_decimal_inverse_rate(): void
    {
        self::assertSame('0.000689655172', (string) (new RateSet(['USD_ARS' => '1450.00']))->factor('ARS', 'USD'));
    }

    #[Test]
    /** A23 */
    public function it_never_triangulates_two_available_rates(): void
    {
        $rates = new RateSet(['USD_ARS' => '1450.00', 'EUR_ARS' => '1562.10']);

        try {
            $rates->factor('EUR', 'USD');
            self::fail('Triangulation must not be attempted.');
        } catch (DomainValidationException $exception) {
            self::assertSame(['exchange_rates'], array_keys($exception->errors));
        }
    }

    #[Test]
    /** A24 */
    public function the_same_currency_needs_no_rates(): void
    {
        self::assertSame('1', (string) (new RateSet([]))->factor('ARS', 'ARS'));
    }
}
