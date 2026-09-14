<?php

return [
    /*
     * Used to sign the client-facing payment status response. Set a dedicated
     * random secret in production; APP_KEY is only a local-development fallback.
     */
    'signing_secret' => env('PAYMENT_API_SIGNING_SECRET', env('APP_KEY')),
];
