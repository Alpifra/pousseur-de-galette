<?php

namespace App\Enum;

enum EventStatus: string
{
    case Draft = 'draft';
    case Live = 'live';
    case Paused = 'paused';
    case Ended = 'ended';

    public function acceptsContributions(): bool
    {
        return self::Live === $this;
    }
}
