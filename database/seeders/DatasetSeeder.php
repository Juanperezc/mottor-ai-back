<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Quotes\ExactJsonDecoder;
use App\Application\Quotes\StoreQuoteBatch;
use Illuminate\Database\Seeder;
use JsonException;
use RuntimeException;
use UnexpectedValueException;

final class DatasetSeeder extends Seeder
{
    private const int BATCH_SIZE = 500;

    /** @throws JsonException */
    public function run(StoreQuoteBatch $storeQuoteBatch, ExactJsonDecoder $decoder): void
    {
        $path = (string) config('seeding.dataset_path');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read dataset at {$path}.");
        }

        $dataset = $decoder->decode($contents);
        $quotes = is_array($dataset) ? ($dataset['quotes'] ?? null) : null;

        if (! is_array($quotes) || ! array_is_list($quotes)) {
            throw new UnexpectedValueException('The dataset must contain a quotes list.');
        }

        foreach (array_chunk($quotes, self::BATCH_SIZE) as $batch) {
            $storeQuoteBatch->execute($batch);
        }
    }
}
