<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\QuoteFixtures;
use Tests\TestCase;

final class ValidateDatasetCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    /** BT-37 */
    public function it_calculates_a_dataset_without_persisting_it(): void
    {
        $path = storage_path('framework/testing/validate-dataset.json');
        $quote = file_get_contents(QuoteFixtures::path('official-case.input.json'));
        self::assertIsString($quote);
        file_put_contents($path, '{"quotes":['.$quote.']}');

        try {
            self::assertSame(0, Artisan::call('quotes:validate-dataset', ['path' => $path]));
            $output = Artisan::output();
            self::assertStringContainsString('"count":1', $output);
            self::assertStringContainsString('"minimum_total":"4722388.00"', $output);
            self::assertStringContainsString('"invalid_quotes":0', $output);
        } finally {
            unlink($path);
        }

        $this->assertDatabaseCount('quotes', 0);
    }

    #[Test]
    /** BT-37 */
    public function it_fails_when_the_dataset_file_does_not_exist(): void
    {
        $this->artisan('quotes:validate-dataset', ['path' => '/tmp/mottor-missing-dataset.json'])
            ->expectsOutputToContain('Dataset not found')
            ->assertFailed();
    }
}
