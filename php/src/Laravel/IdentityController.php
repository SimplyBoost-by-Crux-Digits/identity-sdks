<?php

declare(strict_types=1);

namespace SimplyBoost\Identity\Laravel;

use Illuminate\Http\JsonResponse;

/**
 * GET /simplyboost/identity: a freshly signed identity for the logged-in
 * user ({email, name, ts, user_hash}), or 401 for a guest. The widget calls
 * it on the same origin with the session cookie when the signature in the
 * page is older than eight minutes.
 */
class IdentityController
{
    public function __invoke(WidgetRenderer $renderer): JsonResponse
    {
        $visitor = $renderer->visitor();

        return (new JsonResponse($visitor ?? new \stdClass(), $visitor === null ? 401 : 200))
            ->header('Cache-Control', 'no-store, private');
    }
}
