<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GLPI Middleware API
    |--------------------------------------------------------------------------
    |
    | Base URL of the Node/Express middleware that this dashboard reads from
    | (audits, scanned items, and audit photos). Set MIDDLEWARE_BASE_URL in
    | your .env — this must be reachable both from the PHP server (for JSON)
    | and from the browser (for <img> photo tags).
    |
    */

    'middleware' => [
        'base_url' => env('MIDDLEWARE_BASE_URL', 'http://10.0.0.184:3003'),
        'timeout' => (int) env('MIDDLEWARE_TIMEOUT', 15),
    ],

];
