<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Quotes\StoreQuoteBatch;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    private const int BATCH_SIZE = 500;

    public function run(StoreQuoteBatch $storeQuoteBatch): void
    {
        $count = max(0, (int) config('seeding.quotes', 300));
        $factory = QuoteFactory::new();

        if ($count === 0) {
            return;
        }

        foreach (array_chunk(range(0, $count - 1), self::BATCH_SIZE) as $sequences) {
            $storeQuoteBatch->execute(array_map(
                fn (int $sequence): array => $factory->input($sequence),
                $sequences,
            ));
        }
    }
}
