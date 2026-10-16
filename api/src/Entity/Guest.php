<?php

namespace App\Entity;

use App\Repository\GuestRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GuestRepository::class)]
#[ORM\Table(name: 'guest')]
#[ORM\UniqueConstraint(name: 'uniq_guest_session_token', columns: ['session_token'])]
#[ORM\Index(name: 'idx_guest_event', columns: ['event_id'])]
class Guest
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[Groups(['guest:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'guests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 40)]
    #[Groups(['guest:read'])]
    private string $pseudo;

    #[ORM\Column(length: 64)]
    private string $sessionToken;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['guest:read'])]
    private bool $banned = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    public function __construct(Event $event, string $pseudo, string $sessionToken)
    {
        $this->id = Uuid::v7();
        $this->event = $event;
        $this->pseudo = $pseudo;
        $this->sessionToken = $sessionToken;
        $this->createdAt = new \DateTimeImmutable();
        $this->lastSeenAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getPseudo(): string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): void
    {
        $this->pseudo = $pseudo;
    }

    public function getSessionToken(): string
    {
        return $this->sessionToken;
    }

    public function isBanned(): bool
    {
        return $this->banned;
    }

    public function setBanned(bool $banned): void
    {
        $this->banned = $banned;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function touch(): void
    {
        $this->lastSeenAt = new \DateTimeImmutable();
    }
}
