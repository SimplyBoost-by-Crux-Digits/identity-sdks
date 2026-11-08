<?php
// GET /simplyboost-identity.php: a freshly signed identity for the logged-in
// user, so a page left open longer than ten minutes still starts a verified
// chat. Same origin as your pages; the widget calls it with the session cookie.
require __DIR__ . '/vendor/autoload.php';

use SimplyBoost\Identity\Identity;

session_start();
$user = $_SESSION['user'] ?? null;

header('Content-Type: application/json');
header('Cache-Control: no-store, private');

if (!$user) {
    http_response_code(401);
    echo '{}';
    exit;
}

echo json_encode(
    Identity::visitor(getenv('SIMPLYBOOST_IDENTITY_SECRET'), $user['email'], $user['name'] ?? null),
    JSON_UNESCAPED_SLASHES
);
