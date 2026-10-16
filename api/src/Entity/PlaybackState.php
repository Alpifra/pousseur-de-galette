<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Last known state of the host's Spotify player, refreshed by the playback worker. */
#[ORM\Embeddable]
class PlaybackState
{
    #[ORM\Column(options: ['default' => false])]
    public bool $isPlaying = false;

    #[ORM\Column(length: 64, nullable: true)]
    public ?string $trackId = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $trackName = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $trackArtist = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $coverUrl = null;

    #[ORM\Column(options: ['default' => 0])]
    public int $durationMs = 0;

    #[ORM\Column(options: ['default' => 0])]
    public int $progressMs = 0;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;

    /** False when the host played something outside of the Pousseur de Galette queue. */
    #[ORM\Column(options: ['default' => false])]
    public bool $fromQueue = false;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $deviceName = null;

    #[ORM\Column(length: 64, nullable: true)]
    public ?string $error = null;

    public function remainingMs(): ?int
    {
        if (0 === $this->durationMs) {
            return null;
        }

        return max(0, $this->durationMs - $this->progressMs);
    }
}
