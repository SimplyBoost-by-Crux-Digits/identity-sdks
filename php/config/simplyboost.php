<?php

return [

    /*
    | Your chatbot's id (Dashboard -> Install). Public: it is in every page.
    */
    'bot_id' => env('SIMPLYBOOST_BOT_ID'),

    /*
    | The identity secret (Dashboard -> Install -> Identity verification).
    | Server-side only: keep it in .env or your secret manager, never in a
    | view, a JavaScript bundle or a public repository. If it leaks, rotate it
    | in the dashboard.
    */
    'identity_secret' => env('SIMPLYBOOST_IDENTITY_SECRET'),

    /*
    | The auth guard whose logged-in user is signed (null: the default guard)
    | and the user attributes that hold the email and the display name.
    | For anything more specific, call
    | SimplyBoost\Identity\Laravel\WidgetRenderer::resolveUserUsing(...) in a
    | service provider.
    */
    'guard' => null,
    'email_attribute' => 'email',
    'name_attribute' => 'name',

    /*
    | The refresh endpoint: returns a freshly signed identity for the
    | logged-in user, so a page left open longer than ten minutes still
    | starts a verified chat. The widget calls it on the same origin with the
    | session cookie, so it needs the session middleware ("web").
    */
    'refresh_route' => [
        'enabled' => true,
        'path' => 'simplyboost/identity',
        'middleware' => ['web', 'throttle:60,1'],
    ],

    /*
    | The widget script. Change only if SimplyBoost tells you to.
    */
    'widget_url' => env('SIMPLYBOOST_WIDGET_URL', 'https://get.simplyboost.io/widget.js'),

];
