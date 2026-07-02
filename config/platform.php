<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Target Application
    |--------------------------------------------------------------------------
    |
    | The app this builder shapes. The canvas iframes it, the bridge posts
    | messages to it, and server-side calls hit its /platform/api routes.
    | Locally that's the app at ~/Sites/default served by Herd; in
    | production it's the customer's app at <app>.devdojo.app.
    |
    */

    'app_url' => env('PLATFORM_APP_URL', 'http://default.test'),

    /*
    |--------------------------------------------------------------------------
    | Internal App URL
    |--------------------------------------------------------------------------
    |
    | Where server-to-server bridge calls go. In the shared pod the app is a
    | loopback away (http://127.0.0.1:8000), which is faster and works even
    | before public DNS does. Falls back to the public app_url when unset.
    |
    */

    'app_internal_url' => env('PLATFORM_APP_INTERNAL_URL'),

    /*
    |--------------------------------------------------------------------------
    | Project Name
    |--------------------------------------------------------------------------
    |
    | Display name for the project shown in the builder chrome.
    |
    */

    'app_name' => env('PLATFORM_APP_NAME', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Bridge Token
    |--------------------------------------------------------------------------
    |
    | Shared secret sent with server-to-server calls to the target app.
    | Must match PLATFORM_BRIDGE_TOKEN on the app side when configured.
    |
    */

    'token' => env('PLATFORM_BRIDGE_TOKEN'),

];
