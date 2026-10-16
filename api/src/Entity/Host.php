<?php

namespace App\Entity;

use App\Repository\HostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HostRepository::class)]
#[ORM\Table(name: 'host')]
#[ORM\UniqueConstraint(name: 'uniq_host_email', columns: ['email'])]
class Host implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column]
    private string $password = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $spotifyUserId = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $spotifyProduct = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $spotifyAccessToken = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $spotifyRefreshToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $spotifyTokenExpiresAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Event> */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'host')]
    private Collection $events;

    public function __construct(string $email)
    {
        $this->id = Uuid::v7();
        $this->email = $email;
        $this->createdAt = new \DateTimeImmutable();
        $this->events = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        \assert('' !== $this->email);

        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_HOST']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function eraseCredentials(): void
    {
    }

    public function getSpotifyUserId(): ?string
    {
        return $this->spotifyUserId;
    }

    public function setSpotifyUserId(?string $spotifyUserId): void
    {
        $this->spotifyUserId = $spotifyUserId;
    }

    public function getSpotifyProduct(): ?string
    {
        return $this->spotifyProduct;
    }

    public function setSpotifyProduct(?string $spotifyProduct): void
    {
        $this->spotifyProduct = $spotifyProduct;
    }

    public function hasPremium(): bool
    {
        return 'premium' === $this->spotifyProduct;
    }

    public function getSpotifyAccessToken(): ?string
    {
        return $this->spotifyAccessToken;
    }

    public function setSpotifyAccessToken(?string $spotifyAccessToken): void
    {
        $this->spotifyAccessToken = $spotifyAccessToken;
    }

    public function getSpotifyRefreshToken(): ?string
    {
        return $this->spotifyRefreshToken;
    }

    public function setSpotifyRefreshToken(?string $spotifyRefreshToken): void
    {
        $this->spotifyRefreshToken = $spotifyRefreshToken;
    }

    public function getSpotifyTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->spotifyTokenExpiresAt;
    }

    public function setSpotifyTokenExpiresAt(?\DateTimeImmutable $spotifyTokenExpiresAt): void
    {
        $this->spotifyTokenExpiresAt = $spotifyTokenExpiresAt;
    }

    public function isSpotifyConnected(): bool
    {
        return null !== $this->spotifyRefreshToken;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Event> */
    public function getEvents(): Collection
    {
        return $this->events;
    }
}
