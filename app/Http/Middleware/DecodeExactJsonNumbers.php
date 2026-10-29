<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Quotes\ExactJsonDecoder;
use Closure;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;

final readonly class DecodeExactJsonNumbers
{
    public function __construct(private ExactJsonDecoder $decoder) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isJson() && $request->getContent() !== '') {
            try {
                $decoded = $this->decoder->decode($request->getContent());

                if (is_array($decoded)) {
                    $request->setJson(new InputBag($decoded));
                }
            } catch (JsonException) {
                // Leave malformed JSON to Laravel's normal validation response.
            }
        }

        return $next($request);
    }
}
