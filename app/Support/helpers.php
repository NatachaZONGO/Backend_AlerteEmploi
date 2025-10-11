<?php

if (! function_exists('frontend_url')) {
    function frontend_url(string $path = ''): string
    {
        $base = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:4200')), '/');
        $path = ltrim($path, '/');
        return $path ? "$base/$path" : $base;
    }
}
