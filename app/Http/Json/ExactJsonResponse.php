<?php

declare(strict_types=1);

namespace App\Http\Json;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class ExactJsonResponse extends Response
{
    /** @param array<string, mixed>|list<mixed> $data */
    public function __construct(array $data, int $status = 200, array $headers = [])
    {
        parent::__construct(
            self::encode($data),
            $status,
            ['Content-Type' => 'application/json', ...$headers],
        );
    }

    private static function encode(mixed $value): string
    {
        if ($value instanceof DecimalNumber) {
            return $value->literal;
        }

        if (is_array($value)) {
            $encoded = [];
            if (array_is_list($value)) {
                foreach ($value as $item) {
                    $encoded[] = self::encode($item);
                }

                return '['.implode(',', $encoded).']';
            }

            foreach ($value as $key => $item) {
                $encoded[] = json_encode((string) $key, JSON_THROW_ON_ERROR).':'.self::encode($item);
            }

            return '{'.implode(',', $encoded).'}';
        }

        if (is_float($value)) {
            throw new InvalidArgumentException('Floats are not allowed in exact JSON responses.');
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
