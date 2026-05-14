<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Applies mandatory HTTP security headers to every response.
 *
 * - Content-Security-Policy (script-src self + CDN only)
 * - X-Frame-Options: DENY
 * - X-Content-Type-Options: nosniff
 * - Referrer-Policy: strict-origin
 */
#[AsEventListener(event: 'kernel.response')]
final class SecurityHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;

        $headers->set(
            'Content-Security-Policy',
            "default-src 'self'; ".
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.plot.ly https://unpkg.com; ".
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; ".
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; ".
            "img-src 'self' data: blob:; ".
            "connect-src 'self' https://cdn.plot.ly; ".
            "object-src 'none'; ".
            "base-uri 'self'; ".
            "frame-ancestors 'none';"
        );
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin');
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    }
}
