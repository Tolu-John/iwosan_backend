<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiVersionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $path = $request->path();
        $version = str_starts_with($path, 'api/v1') ? 'v1' : 'unversioned';
        $response->headers->set('X-API-Version', $version);

        if ($version === 'unversioned') {
            $response->headers->set('Warning', '299 - Deprecated API route, use /api/v1');
        }

        return $response;
    }
}
