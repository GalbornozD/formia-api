<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Con supports_credentials=true el spec de CORS prohíbe '*': tiene que
    // ser una lista explícita de orígenes (el SPA Angular).
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:4200')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Requerido para que la cookie de sesión httpOnly de Sanctum viaje en
    // requests cross-origin desde el SPA.
    'supports_credentials' => true,

];
