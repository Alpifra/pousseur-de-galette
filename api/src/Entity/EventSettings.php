<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Embeddable]
class EventSettings
{
    #[ORM\Column(options: ['default' => true])]
    #[Groups(['event:read'])]
    public bool $allowDownvotes = true;

    #[ORM\Column(options: ['default' => -5])]
    #[Assert\LessThan(0)]
    public int $rejectionThreshold = -5;

    #[ORM\Column(options: ['default' => 5])]
    #[Assert\Positive]
    #[Groups(['event:read'])]
    public int $maxPendingPerGuest = 5;

    #[ORM\Column(options: ['default' => 120])]
    #[Assert\PositiveOrZero]
    public int $replayDelayMinutes = 120;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['event:read'])]
    public bool $blockExplicit = false;

    #[ORM\Column(options: ['default' => 600000])]
    #[Assert\Positive]
    public int $maxTrackDurationMs = 600_000;

    #[ORM\Column(options: ['default' => false])]
    public bool $hostApproval = false;

    /** How long before the end of the current track the next one is pushed to Spotify. */
    #[ORM\Column(options: ['default' => 15000])]
    #[Assert\Range(min: 5000, max: 60000)]
    public int $lockLeadMs = 15_000;
}
