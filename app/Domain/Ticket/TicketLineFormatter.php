<?php

declare(strict_types=1);

namespace App\Domain\Ticket;

final class TicketLineFormatter
{
    /** @return list<string> */
    public function format(string $text, int $width = 28, int $maxLines = 4): array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($current !== '' && mb_strlen($current.' '.$word) <= $width) {
                $current .= ' '.$word;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            while (mb_strlen($word) > $width) {
                $lines[] = mb_substr($word, 0, $width);
                $word = mb_substr($word, $width);
            }

            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim(mb_substr($lines[$maxLines - 1], 0, $width - 1)).'…';
        }

        return $lines;
    }
}
