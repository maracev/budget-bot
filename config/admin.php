<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin bootstrap password
    |--------------------------------------------------------------------------
    |
    | Used by the app:create-admin command when --password is not provided.
    | Read through config so it keeps working with a cached configuration.
    |
    */

    'password' => env('ADMIN_PASSWORD'),
];
