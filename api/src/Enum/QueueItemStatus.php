<?php

namespace App\Enum;

enum QueueItemStatus: string
{
    case Pending = 'pending';
    case Locked = 'locked';
    case Playing = 'playing';
    case Played = 'played';
    case Removed = 'removed';
    case Rejected = 'rejected';

    public function isVotable(): bool
    {
        return self::Pending === $this;
    }

    /** A track that still has to be played: it blocks a new proposal of the same track. */
    public function isUpcoming(): bool
    {
        return \in_array($this, [self::Pending, self::Locked, self::Playing], true);
    }

    /** @return list<string> */
    public static function upcomingValues(): array
    {
        return [self::Pending->value, self::Locked->value, self::Playing->value];
    }
}
