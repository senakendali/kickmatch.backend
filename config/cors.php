<?php

return [

    'paths' => [
        'api/*',        // untuk route api biasa
        'sync/*',       // tambahkan ini supaya /sync/matches bisa kena CORS
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed CORS Origins
    |--------------------------------------------------------------------------
    |
    | Specify the origins that are allowed to send requests to your application.
    | By default, we allow all origins.
    |
    */
    'allowed_origins' => [
        'http://localhost:8080',  // Frontend app URL (replace with the correct URL)
        'https://cjmanajemen.co.id',  // Additional frontend app URL
        'http://192.168.1.11:8000',
        'http://192.168.1.4:8000',
        // Add more allowed origins here
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed CORS Methods
    |--------------------------------------------------------------------------
    |
    | Define the HTTP methods allowed in CORS requests.
    |
    */
    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'OPTIONS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed CORS Headers
    |--------------------------------------------------------------------------
    |
    | Define the HTTP headers that are allowed in CORS requests.
    |
    */
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-Socket-Id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exposed CORS Headers
    |--------------------------------------------------------------------------
    |
    | Specify the response headers that should be exposed to the browser.
    |
    */
    'exposed_headers' => ['Content-Disposition'],

    /*
    |--------------------------------------------------------------------------
    | Support Credentials
    |--------------------------------------------------------------------------
    |
    | If true, cookies are allowed to be included in cross-origin requests.
    | Ensure that the `withCredentials` option is enabled in your HTTP requests.
    |
    */
    'supports_credentials' => true,

    /*
    |--------------------------------------------------------------------------
    | Preflight Max Age
    |--------------------------------------------------------------------------
    |
    | Set the max age of the preflight OPTIONS request cache. It defines how
    | long the browser can cache the results of a preflight request before
    | sending another preflight request.
    |
    */
    'max_age' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Allowed Hosts
    |--------------------------------------------------------------------------
    |
    | Here you may specify a list of hosts that should be allowed by your CORS
    | middleware. This option can be useful if you need to restrict CORS to
    | specific subdomains or domains.
    |
    */
    'hosts' => [],
];
