<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers applied to every web response.
 *
 * Addresses audit findings #2 (missing headers) and #10 (X-Powered-By leak).
 * Standards reference: OWASP Secure Headers Project, NCA cybersecurity controls.
 *
 * HSTS is only emitted over HTTPS — emitting it over HTTP is a no-op per
 * RFC 6797 §7.2 and would pin the header in dev environments where we
 * intentionally run http://localhost.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload',
            );
        }

        // CSP: fonts are now self-hosted (audit #11), so we drop the Google
        // Fonts origins and only allow 'self' for fonts/styles/scripts. Inline
        // styles still allowed for the handful of view-level style= attributes
        // and Alpine directives; migrating those to classes can tighten this
        // further in a future pass.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self' data:",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // Remove PHP version fingerprint (audit #10). Also handled by
        // `expose_php = Off` in php.ini — this is belt-and-suspenders.
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
