<?php

namespace App\Dto;

use App\Entity\Track;

readonly class PlaybackSnapshot
{
    public function __construct(
        public bool $isPlaying,
        public ?Track $track,
        public int $progressMs,
        public ?string $deviceId,
        public ?string $deviceName,
    ) {
    }

    public function remainingMs(): ?int
    {
        if (null === $this->track || 0 === $this->track->durationMs) {
            return null;
        }

        return max(0, $this->track->durationMs - $this->progressMs);
    }
}
