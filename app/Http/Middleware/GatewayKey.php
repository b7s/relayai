<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class GatewayKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config()->string('relayai.gateway_key', '');

        if ($key === '') {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $provided = $request->bearerToken();

        if ($provided !== $key) {
            return response()->json([
                'type' => 'https://tools.ietf.org/html/rfc9457',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Invalid or missing gateway API key',
            ], 401);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
