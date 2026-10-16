<?php

namespace App\Service;

use App\Entity\Event;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/** Pushes event updates to the guests through Mercure. Best effort: a hub outage must not break the API. */
class RealtimePublisher
{
    private const TOPIC_PREFIX = 'https://pousseur-de-galette.app/events/';

    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        #[Autowire('%env(default::MERCURE_PUBLIC_URL)%')]
        private ?string $hubUrl = null,
    ) {
    }

    public function topicFor(Event $event): string
    {
        return self::TOPIC_PREFIX.$event->getId();
    }

    public function hubUrl(): ?string
    {
        return '' === $this->hubUrl ? null : $this->hubUrl;
    }

    /** @param array<string, mixed> $payload */
    public function publish(Event $event, string $type, array $payload): void
    {
        $data = json_encode(['type' => $type, ...$payload], \JSON_THROW_ON_ERROR);

        try {
            $this->hub->publish(new Update($this->topicFor($event), $data));
        } catch (\Throwable $e) {
            $this->logger->warning('Mercure publish failed', ['event' => (string) $event->getId(), 'error' => $e->getMessage()]);
        }
    }
}
