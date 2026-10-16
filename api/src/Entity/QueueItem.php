<?php

namespace App\Entity;

use App\Enum\QueueItemStatus;
use App\Repository\QueueItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: QueueItemRepository::class)]
#[ORM\Table(name: 'queue_item')]
#[ORM\Index(name: 'idx_queue_item_ranking', columns: ['event_id', 'status', 'pinned', 'score', 'created_at'])]
#[ORM\Index(name: 'idx_queue_item_track', columns: ['event_id', 'track_spotify_id'])]
class QueueItem
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[Groups(['queue:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'queueItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\Embedded(class: Track::class, columnPrefix: 'track_')]
    #[Groups(['queue:read'])]
    private Track $track;

    /** Null for tracks the host played outside of the queue. */
    #[ORM\ManyToOne(targetEntity: Guest::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Guest $proposedBy;

    #[ORM\Column(enumType: QueueItemStatus::class, options: ['default' => 'pending'])]
    #[Groups(['queue:read'])]
    private QueueItemStatus $status = QueueItemStatus::Pending;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['queue:read'])]
    private bool $pinned = false;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['queue:read'])]
    private int $score = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $upvotes = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $downvotes = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lockedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $playedAt = null;

    /** @var Collection<int, Vote> */
    #[ORM\OneToMany(targetEntity: Vote::class, mappedBy: 'queueItem', orphanRemoval: true)]
    private Collection $votes;

    public function __construct(Event $event, Track $track, ?Guest $proposedBy)
    {
        $this->id = Uuid::v7();
        $this->event = $event;
        $this->track = $track;
        $this->proposedBy = $proposedBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->votes = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getTrack(): Track
    {
        return $this->track;
    }

    public function getProposedBy(): ?Guest
    {
        return $this->proposedBy;
    }

    public function getStatus(): QueueItemStatus
    {
        return $this->status;
    }

    public function isPinned(): bool
    {
        return $this->pinned;
    }

    public function setPinned(bool $pinned): void
    {
        $this->pinned = $pinned;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getUpvotes(): int
    {
        return $this->upvotes;
    }

    public function getDownvotes(): int
    {
        return $this->downvotes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLockedAt(): ?\DateTimeImmutable
    {
        return $this->lockedAt;
    }

    public function getPlayedAt(): ?\DateTimeImmutable
    {
        return $this->playedAt;
    }

    /** @return Collection<int, Vote> */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function lock(): void
    {
        $this->status = QueueItemStatus::Locked;
        $this->lockedAt = new \DateTimeImmutable();
    }

    public function unlock(): void
    {
        $this->status = QueueItemStatus::Pending;
        $this->lockedAt = null;
    }

    public function startPlaying(): void
    {
        $this->status = QueueItemStatus::Playing;
        $this->playedAt = new \DateTimeImmutable();
    }

    public function markPlayed(): void
    {
        $this->status = QueueItemStatus::Played;
        $this->playedAt ??= new \DateTimeImmutable();
    }

    public function remove(): void
    {
        $this->status = QueueItemStatus::Removed;
    }

    public function reject(): void
    {
        $this->status = QueueItemStatus::Rejected;
    }

    public function recountVotes(): void
    {
        $up = 0;
        $down = 0;
        foreach ($this->votes as $vote) {
            if ($vote->getValue() > 0) {
                ++$up;
            } elseif ($vote->getValue() < 0) {
                ++$down;
            }
        }

        $this->upvotes = $up;
        $this->downvotes = $down;
        $this->score = $up - $down;
    }
}
