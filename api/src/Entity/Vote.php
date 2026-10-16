<?php

namespace App\Entity;

use App\Repository\VoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\Table(name: 'vote')]
#[ORM\UniqueConstraint(name: 'uniq_vote_item_guest', columns: ['queue_item_id', 'guest_id'])]
class Vote
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: QueueItem::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private QueueItem $queueItem;

    #[ORM\ManyToOne(targetEntity: Guest::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Guest $guest;

    #[ORM\Column(type: 'smallint')]
    private int $value;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(QueueItem $queueItem, Guest $guest, int $value)
    {
        $this->id = Uuid::v7();
        $this->queueItem = $queueItem;
        $this->guest = $guest;
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getQueueItem(): QueueItem
    {
        return $this->queueItem;
    }

    public function getGuest(): Guest
    {
        return $this->guest;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setValue(int $value): void
    {
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
