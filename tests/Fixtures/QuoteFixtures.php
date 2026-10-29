<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Application\Quotes\ExactJsonDecoder;
use RuntimeException;

final class QuoteFixtures
{
    public const string SOURCE_COMMIT = '3ef1401a69e29d831d43ba67364c10858c6a2865';

    /** @var array<string, string> */
    public const array SHA256 = [
        'official-case.expected.json' => '13c8a4ee5678fdb83dffbff2331f3cebb9ff6a470bd5aee268e69ca5e428fab1',
        'official-case.input.json' => '2515085beaf78c15f42fa62f60131941fcba7110f3654f154fe8c1218fcdefba',
        'official-case.list-usd.expected.json' => 'bc02110a59e8bf00549c1b2b12589c90ec016c378de4b01ce5d7d44160e4c1d5',
        'second-case.expected.json' => '5f776d13ca92af132489dd9ef3ace93cfb6e8010f1e49b43f2f6d430f2a0595e',
        'second-case.input.json' => 'cf0bc4d1f4f99347f53d486dee7f202349903497d07affe3c1b1cf899097296c',
        'ticket-cases.json' => '5ddfa28483e6083b5e0e206d74c2a98cf4d61a4eaff0d893ab4f8713a078aaf3',
    ];

    /** @return array<string, mixed> */
    public static function official(): array
    {
        return self::load('official-case.input.json');
    }

    /** @return array<string, mixed> */
    public static function officialExpected(): array
    {
        return self::load('official-case.expected.json');
    }

    /** @return array<string, mixed> */
    public static function officialListUsdExpected(): array
    {
        return self::load('official-case.list-usd.expected.json');
    }

    /** @return array<string, mixed> */
    public static function second(): array
    {
        return self::load('second-case.input.json');
    }

    /** @return array<string, mixed> */
    public static function secondExpected(): array
    {
        return self::load('second-case.expected.json');
    }

    /** @return array<string, mixed> */
    public static function ticketCases(): array
    {
        return self::load('ticket-cases.json');
    }

    public static function path(string $name): string
    {
        return __DIR__.DIRECTORY_SEPARATOR.$name;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public static function exact(array $input): array
    {
        return (new ExactJsonDecoder)->decode(json_encode($input, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private static function load(string $name): array
    {
        $contents = file_get_contents(self::path($name));
        if ($contents === false) {
            throw new RuntimeException("Unable to read fixture {$name}.");
        }

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }
}
