<?php
// Plain PHP: print this just before </body> on every page of your portal.
// composer require simplyboost/identity
require __DIR__ . '/vendor/autoload.php';

use SimplyBoost\Identity\Identity;
use SimplyBoost\Identity\Widget;

session_start();
$user = $_SESSION['user'] ?? null; // however your portal keeps the login

// Read on the server only. The secret must never be printed into a page.
$secret = getenv('SIMPLYBOOST_IDENTITY_SECRET');
$visitor = ($user && $secret) ? Identity::visitor($secret, $user['email'], $user['name'] ?? null) : null;

// Widget::snippet escapes every value for the <script> element.
echo Widget::snippet(getenv('SIMPLYBOOST_BOT_ID'), $visitor, [
    'refresh_url' => '/simplyboost-identity.php',
]);
