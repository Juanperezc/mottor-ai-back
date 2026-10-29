<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Dedoc\Scramble\Attributes\Response;

final class HealthController extends Controller
{
    /** @return array{status: 'ok'} */
    #[Response(type: "array{status: 'ok'}")]
    public function __invoke(): array
    {
        return ['status' => 'ok'];
    }
}
