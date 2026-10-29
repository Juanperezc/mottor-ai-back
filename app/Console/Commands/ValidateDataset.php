<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Quotes\DecimalInputNormalizer;
use App\Application\Quotes\ExactJsonDecoder;
use App\Domain\Quote\QuoteCalculator;
use Illuminate\Console\Command;
use JsonException;

final class ValidateDataset extends Command
{
    protected $signature = 'quotes:validate-dataset {path=/inputs/dataset.json}';

    protected $description = 'Calculate every quote in a challenge dataset without persisting it';

    public function handle(DecimalInputNormalizer $normalizer, ExactJsonDecoder $decoder, QuoteCalculator $calculator): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("Dataset not found: {$path}");

            return self::FAILURE;
        }

        try {
            $dataset = $decoder->decode(file_get_contents($path));
        } catch (JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $minimum = null;
        $minimumBudget = null;
        $negatives = 0;
        $invalid = 0;

        foreach ($dataset['quotes'] ?? [] as $input) {
            try {
                $quote = $calculator->calculate($normalizer->normalize($input));
                if ($quote->total->isNegative()) {
                    $negatives++;
                }
                if ($minimum === null || $minimum->isGreaterThan($quote->total)) {
                    $minimum = $quote->total;
                    $minimumBudget = $quote->budgetId;
                }
            } catch (\Throwable $exception) {
                $invalid++;
            }
        }

        $this->line(json_encode([
            'count' => count($dataset['quotes'] ?? []),
            'minimum_total' => $minimum?->toString(),
            'minimum_budget_id' => $minimumBudget,
            'negative_totals' => $negatives,
            'invalid_quotes' => $invalid,
        ], JSON_THROW_ON_ERROR));

        return $negatives === 0 && $invalid === 0 ? self::SUCCESS : self::FAILURE;
    }
}
