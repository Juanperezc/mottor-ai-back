<?php

declare(strict_types=1);

namespace App\Application\Quotes;

use JsonException;

final class ExactJsonDecoder
{
    /**
     * Decode JSON numbers as strings so decimal literals never pass through float.
     *
     * @throws JsonException
     */
    public function decode(string $json): mixed
    {
        $quotedNumbers = '';
        $length = strlen($json);
        $inString = false;
        $escaped = false;

        for ($index = 0; $index < $length; $index++) {
            $character = $json[$index];

            if ($inString) {
                $quotedNumbers .= $character;

                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;
                $quotedNumbers .= $character;

                continue;
            }

            if (($character === '-' || ctype_digit($character))
                && preg_match('/\G-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?/', $json, $match, 0, $index) === 1
            ) {
                $quotedNumbers .= '"'.$match[0].'"';
                $index += strlen($match[0]) - 1;

                continue;
            }

            $quotedNumbers .= $character;
        }

        return json_decode($quotedNumbers, true, 512, JSON_THROW_ON_ERROR);
    }
}
