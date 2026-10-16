<?php

namespace App\Dto;

readonly class SpotifyDevice
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public bool $isActive,
        public bool $isRestricted,
    ) {
    }
}
