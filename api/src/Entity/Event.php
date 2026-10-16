<?php

namespace App\Entity;

use App\Enum\EventStatus;
use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
#[ORM\UniqueConstraint(name: 'uniq_event_join_code', columns: ['join_code'])]
#[ORM\Index(name: 'idx_event_status', columns: ['status'])]
class Event
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[Groups(['event:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Host::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Host $host;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['event:read'])]
    private string $name;

    #[ORM\Column(length: 12)]
    #[Groups(['event:read'])]
    private string $joinCode;

    #[ORM\Column(enumType: EventStatus::class, options: ['default' => 'draft'])]
    #[Groups(['event:read'])]
    private EventStatus $status = EventStatus::Draft;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $scheduledFor = null;

    #[ORM\Embedded(class: EventSettings::class)]
    #[Groups(['event:read'])]
    private EventSettings $settings;

    #[ORM\Embedded(class: PlaybackState::class)]
    private PlaybackState $playback;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $spotifyDeviceId = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    /** @var Collection<int, Guest> */
    #[ORM\OneToMany(targetEntity: Guest::class, mappedBy: 'event', orphanRemoval: true)]
    private Collection $guests;

    /** @var Collection<int, QueueItem> */
    #[ORM\OneToMany(targetEntity: QueueItem::class, mappedBy: 'event', orphanRemoval: true)]
    private Collection $queueItems;

    public function __construct(Host $host, string $name, string $joinCode)
    {
        $this->id = Uuid::v7();
        $this->host = $host;
        $this->name = $name;
        $this->joinCode = $joinCode;
        $this->settings = new EventSettings();
        $this->playback = new PlaybackState();
        $this->createdAt = new \DateTimeImmutable();
        $this->guests = new ArrayCollection();
        $this->queueItems = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getHost(): Host
    {
        return $this->host;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getJoinCode(): string
    {
        return $this->joinCode;
    }

    public function getStatus(): EventStatus
    {
        return $this->status;
    }

    public function setStatus(EventStatus $status): void
    {
        $this->status = $status;
        $this->endedAt = EventStatus::Ended === $status ? new \DateTimeImmutable() : null;
    }

    public function getScheduledFor(): ?\DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function setScheduledFor(?\DateTimeImmutable $scheduledFor): void
    {
        $this->scheduledFor = $scheduledFor;
    }

    public function getSettings(): EventSettings
    {
        return $this->settings;
    }

    public function getPlayback(): PlaybackState
    {
        return $this->playback;
    }

    public function getSpotifyDeviceId(): ?string
    {
        return $this->spotifyDeviceId;
    }

    public function setSpotifyDeviceId(?string $spotifyDeviceId): void
    {
        $this->spotifyDeviceId = $spotifyDeviceId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    /** @return Collection<int, Guest> */
    public function getGuests(): Collection
    {
        return $this->guests;
    }

    /** @return Collection<int, QueueItem> */
    public function getQueueItems(): Collection
    {
        return $this->queueItems;
    }
}
