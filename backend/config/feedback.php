<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public URL
    |--------------------------------------------------------------------------
    | Where the Vue board lives — used to build the direct board link and the
    | widget script src returned by the admin "how to embed" endpoint. Reuses
    | FRONTEND_URL (same value backs Sanctum stateful cookies).
    */
    'public_url' => rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Reserved slugs
    |--------------------------------------------------------------------------
    | Slugs that collide with system routes (admin area, static assets, auth
    | pages) and that we don't want board owners to grab. The slug validation
    | rule rejects these during board creation.
    */
    'reserved_slugs' => [
        'app', 'api', 'admin', 'auth', 'sanctum',
        'widget.js', 'widget', 'favicon.ico', 'robots.txt', 'sitemap.xml',
        'register', 'login', 'logout', 'signup', 'signin',
        'settings', 'account', 'billing',
        'about', 'help', 'docs', 'support', 'contact',
        'privacy', 'terms', 'legal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trash retention (days)
    |--------------------------------------------------------------------------
    | Soft-deleted boards are purged after this many days by the
    | PurgeTrashedBoards command. Iteration 4.
    */
    'trash_retention_days' => (int) env('FEEDBACK_TRASH_RETENTION_DAYS', 30),
];
