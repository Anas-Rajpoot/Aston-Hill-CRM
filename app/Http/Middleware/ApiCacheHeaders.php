<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds Cache-Control and ETag headers to GET API responses.
 *
 * - Sets short public cache (30s client, 60s proxy) for list/report GETs.
 * - Computes ETag from response body hash; returns 304 if client has it.
 * - Skips non-GET and non-200 responses.
 */
class ApiCacheHeaders
{
    public function handle(Request $request, Closure $next, int $maxAge = 30, int $sMaxAge = 60): Response
    {
        $response = $next($request);

        if (!$request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $content = $response->getContent();
        $etag = '"' . md5($content) . '"';

        // Check If-None-Match → return 304 if unchanged
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->withHeaders([
                'ETag' => $etag,
                'Cache-Control' => "public, max-age={$maxAge}, s-maxage={$sMaxAge}",
            ]);
        }

        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', "public, max-age={$maxAge}, s-maxage={$sMaxAge}");
        $response->headers->set('Vary', 'Accept, Authorization');

        return $response;
    }
}
