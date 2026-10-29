<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Quotes\DecimalInputNormalizer;
use App\Application\Quotes\ExactJsonDecoder;
use App\Domain\Exception\DomainValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\QuoteFixtures;

final class ExactJsonInputTest extends TestCase
{
    #[Test]
    /** BT-26 */
    public function it_preserves_raw_json_number_tokens_without_creating_floats(): void
    {
        $decoded = (new ExactJsonDecoder)->decode(
            '{"amount":99999999999999.99,"quantity":2,"scientific":1e2,"text":"3.14"}',
        );

        self::assertSame('99999999999999.99', $decoded['amount']);
        self::assertSame('2', $decoded['quantity']);
        self::assertSame('1e2', $decoded['scientific']);
        self::assertSame('3.14', $decoded['text']);
    }

    #[Test]
    /** BT-26 */
    public function the_normalizer_rejects_a_php_float_instead_of_approximating_it(): void
    {
        $input = QuoteFixtures::official();
        $input['budget']['items'][0]['unit_price'] = 0.1 + 0.2;

        try {
            (new DecimalInputNormalizer)->normalize($input);
            self::fail('A decoded float must never be used as a decimal source.');
        } catch (DomainValidationException $exception) {
            self::assertSame(
                ['budget.items.0.unit_price'],
                array_keys($exception->errors),
            );
        }
    }
}
