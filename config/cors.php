<?php

return [
    'paths' => ['*'],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],
    'allowed_headers' => ['Content-Type'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
