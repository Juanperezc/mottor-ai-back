<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rules;

use App\Domain\Money\Money;
use App\Domain\Rules\CalculationState;
use App\Domain\Rules\CommercialRule;
use App\Domain\Rules\RuleEffect;
use App\Domain\Rules\RuleEngine;
use App\Domain\Rules\RuleRegistry;
use PHPUnit\Framework\Attributes\Test;

final class RuleEngineOpenClosedTest extends RuleTestCase
{
    #[Test]
    /** A18, BT-13 */
    public function a_fake_rule_registered_only_in_the_test_runs_in_the_pipeline(): void
    {
        $registry = new RuleRegistry([
            'fake' => fn (array $configuration, CalculationState $state): CommercialRule => new class implements CommercialRule
            {
                public function apply(CalculationState $state): RuleEffect
                {
                    return new RuleEffect(
                        $state->total,
                        Money::of('5.00'),
                        Money::zero(),
                        'Fake rule',
                    );
                }
            },
        ]);

        $result = (new RuleEngine($registry))->calculate(
            $this->state('100.00', '100.00'),
            [['type' => 'fake']],
        );

        self::assertSame('105.00', $result['state']->total->toString());
        self::assertCount(1, $result['steps']);
        self::assertSame('fake', $result['steps'][0]->type);
        self::assertSame('5.00', $result['steps'][0]->amount->toString());
        self::assertSame('Fake rule', $result['steps'][0]->label);
    }
}
