<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Enforces 60 req/min per client IP on all /api/* routes
 * (token_bucket limiter declared in framework.yaml as `api`).
 */
#[AsEventListener(event: 'kernel.request', priority: 20)]
final class ApiRateLimitListener
{
    public function __construct(
        private readonly RateLimiterFactory $apiLimiter,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }
        // Let Swagger UI load without hammering the limiter.
        if (str_starts_with($request->getPathInfo(), '/api/docs')) {
            return;
        }

        $limit = $this->apiLimiter->create($request->getClientIp() ?? 'anon')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                (int) max(1, $limit->getRetryAfter()->getTimestamp() - time()),
                'Quota API dépassé (60 req/min). Réessayez dans quelques instants.'
            );
        }
    }
}
