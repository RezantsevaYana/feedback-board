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
    'public_url' => rtrim(env('FRONTEND_URL', 'http://localhost:5176'), '/'),

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

    /*
    |--------------------------------------------------------------------------
    | Public email domains
    |--------------------------------------------------------------------------
    | Addresses on these domains don't identify a company — the owner's inbox
    | is personal, not corporate. For such owners we store project.team_domain
    | as null (nobody auto-gets the "team" badge by matching domain). The list
    | is intentionally conservative; adding a provider later is a one-liner.
    */
    'public_email_domains' => [
        'gmail.com', 'googlemail.com',
        'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
        'yahoo.com', 'yahoo.ru',
        'icloud.com', 'me.com', 'mac.com',
        'mail.ru', 'inbox.ru', 'bk.ru', 'list.ru',
        'yandex.ru', 'yandex.com', 'ya.ru',
        'proton.me', 'protonmail.com',
        'rambler.ru',
    ],
];
