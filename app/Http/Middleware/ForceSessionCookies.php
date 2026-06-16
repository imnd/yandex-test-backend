<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class ForceSessionCookies
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isProductionOrHttps = config('app.env') === 'production'
            || $request->secure()
            || config('session.force_secure', false);

        if ($isProductionOrHttps && isset($response->headers)) {
            foreach ($response->headers->getCookies() as $cookie) {
                if (in_array($cookie->getName(), ['laravel_session', 'XSRF-TOKEN']) || str_ends_with($cookie->getName(), '-session')) {
                    $newCookie = new Cookie(
                        $cookie->getName(),
                        $cookie->getValue(),
                        $cookie->getExpiresTime(),
                        $cookie->getPath(),
                        $cookie->getDomain(),
                        true, // secure
                        $cookie->isHttpOnly(),
                        $cookie->isRaw(),
                        $request->secure() ? 'none' : 'lax' // sameSite
                    );
                    $response->headers->setCookie($newCookie);
                }
            }
        }

        return $response;
    }
}
