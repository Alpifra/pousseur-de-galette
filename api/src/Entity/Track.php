<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Embeddable]
class Track
{
    public function __construct(
        #[ORM\Column(length: 64)]
        #[Groups(['queue:read'])]
        public string $spotifyId,
        #[ORM\Column(length: 255)]
        #[Groups(['queue:read'])]
        public string $name,
        #[ORM\Column(length: 255)]
        #[Groups(['queue:read'])]
        public string $artist,
        #[ORM\Column(length: 255, nullable: true)]
        public ?string $album = null,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        #[Groups(['queue:read'])]
        public ?string $coverUrl = null,
        #[ORM\Column(options: ['default' => 0])]
        #[Groups(['queue:read'])]
        public int $durationMs = 0,
        #[ORM\Column(options: ['default' => false])]
        #[Groups(['queue:read'])]
        public bool $explicit = false,
    ) {
    }

    public function uri(): string
    {
        return 'spotify:track:'.$this->spotifyId;
    }
}
