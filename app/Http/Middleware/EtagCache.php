<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP caching: ETag / If-None-Match support for GET requests.
 * Computes ETag from a hash of the response body (or from a custom generator callback).
 * Returns 304 Not Modified when If-None-Match matches, reducing bandwidth and load.
 *
 * Usage: apply to read-heavy GET routes. Optionally set Cache-Control in controller.
 * Example in route: Route::get('/users/filters', ...)->middleware('etag.cache');
 */
class EtagCache
{
    public function handle(Request $request, Closure $next, ?string $maxAge = '300'): Response
    {
        $response = $next($request);

        if (!$request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return $response;
        }

        $etag = '"' . md5($content) . '"';
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'private, max-age=' . (int) $maxAge);

        if (trim($request->header('If-None-Match', '')) === $etag) {
            return response('', 304)->withHeaders([
                'ETag' => $etag,
                'Cache-Control' => 'private, max-age=' . (int) $maxAge,
            ]);
        }

        return $response;
    }
}
