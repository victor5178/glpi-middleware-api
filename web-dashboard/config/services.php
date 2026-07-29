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

    /*
    |--------------------------------------------------------------------------
    | GLPI REST API
    |--------------------------------------------------------------------------
    |
    | Used by the dashboard's search to query GLPI (10.0.0.11) for assets by
    | serial / user / id. Credentials stay server-side.
    |
    */
    'glpi' => [
        'base_url' => env('GLPI_URL', 'http://10.0.0.11/glpi/apirest.php'),
        'app_token' => env('GLPI_APP_TOKEN', ''),
        'login' => env('GLPI_LOGIN', ''),
        'password' => env('GLPI_PASSWORD', ''),
        'timeout' => (int) env('GLPI_TIMEOUT', 15),
    ],

];
