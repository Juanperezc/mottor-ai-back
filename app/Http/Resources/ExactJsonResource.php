<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Json\ExactJsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

abstract class ExactJsonResource extends JsonResource
{
    public int $status = 200;

    public function toResponse($request): Response
    {
        return new ExactJsonResponse($this->resolve($request), $this->status ?? 200);
    }
}
