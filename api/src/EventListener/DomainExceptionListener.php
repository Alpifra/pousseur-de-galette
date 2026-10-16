<?php

namespace App\EventListener;

use App\Exception\DomainException;
use App\Spotify\SpotifyRateLimitException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/** Turns business errors into the {error, message} payload the guest app expects. */
#[AsEventListener]
class DomainExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof DomainException) {
            $event->setResponse(new JsonResponse([
                'error' => $exception->errorCode,
                'message' => $exception->getMessage(),
                'context' => $exception->context,
            ], $exception->statusCode));

            return;
        }

        if ($exception instanceof SpotifyRateLimitException) {
            $event->setResponse(new JsonResponse([
                'error' => 'rate_limited',
                'message' => 'Spotify est saturé, réessaie dans quelques secondes.',
                'context' => ['retryAfter' => $exception->retryAfter],
            ], 429, ['Retry-After' => (string) $exception->retryAfter]));
        }
    }
}
