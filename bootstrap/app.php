<?php

use App\Domain\Exception\DomainValidationException;
use App\Http\Middleware\DecodeExactJsonNumbers;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [DecodeExactJsonNumbers::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('health', 'quotes', 'quotes/*', 'imports', 'imports/*') || $request->expectsJson(),
        );
        $exceptions->render(function (DomainValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors,
            ], 422);
        });
        $exceptions->render(function (ModelNotFoundException $exception) {
            return response()->json(['message' => 'Resource not found.'], 404);
        });
    })->create();
