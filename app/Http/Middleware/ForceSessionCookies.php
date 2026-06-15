<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceSessionCookies
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isProductionOrHttps = config('app.env') === 'production'
            || $request->secure()
            || str_contains($request->getHost(), 'onrender.com');

        if ($isProductionOrHttps) {
            config(['session.secure' => true]);
            config(['session.same_site' => 'none']);
        }

        $response = $next($request);

        $response->headers->set('X-Debug-Middleware', 'Ran');
        $response->headers->set('X-Debug-Is-Prod', $isProductionOrHttps ? 'yes' : 'no');
        $response->headers->set('X-Debug-Same-Site', config('session.same_site'));

        return $response;
    }
}
