<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ETagMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $content = (string) $response->getContent();
        if ($content === '') {
            return $response;
        }

        $etag = '"' . sha1($content) . '"';
        $response->headers->set('ETag', $etag);

        $ifNoneMatch = trim((string) $request->headers->get('If-None-Match', ''));
        if ($this->etagMatches($ifNoneMatch, $etag)) {
            return response('', 304, [
                'ETag' => $etag,
            ]);
        }

        $ifModifiedSince = $request->headers->get('If-Modified-Since');
        $lastModified = $response->headers->get('Last-Modified');
        if ($ifModifiedSince && $lastModified) {
            $ifModifiedSinceTs = strtotime((string) $ifModifiedSince);
            $lastModifiedTs = strtotime((string) $lastModified);
            if ($ifModifiedSinceTs !== false && $lastModifiedTs !== false && $ifModifiedSinceTs >= $lastModifiedTs) {
                return response('', 304, [
                    'ETag' => $etag,
                    'Last-Modified' => $lastModified,
                ]);
            }
        }

        return $response;
    }

    private function etagMatches(string $ifNoneMatch, string $etag): bool
    {
        if ($ifNoneMatch === '') {
            return false;
        }

        if ($ifNoneMatch === '*' || $ifNoneMatch === $etag) {
            return true;
        }

        $normalized = trim(str_replace('W/', '', $ifNoneMatch));
        if ($normalized === $etag) {
            return true;
        }

        $parts = array_map('trim', explode(',', $ifNoneMatch));
        foreach ($parts as $part) {
            $candidate = trim(str_replace('W/', '', $part));
            if ($candidate === $etag) {
                return true;
            }
        }

        return false;
    }
}

