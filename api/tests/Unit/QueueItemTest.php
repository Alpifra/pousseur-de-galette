<?php

namespace App\Tests\Unit;

use App\Entity\Event;
use App\Entity\Guest;
use App\Entity\Host;
use App\Entity\QueueItem;
use App\Entity\Track;
use App\Entity\Vote;
use App\Enum\QueueItemStatus;
use PHPUnit\Framework\TestCase;

class QueueItemTest extends TestCase
{
    public function testScoreIsUpvotesMinusDownvotes(): void
    {
        $item = $this->item();
        $item->getVotes()->add(new Vote($item, $this->guest($item), 1));
        $item->getVotes()->add(new Vote($item, $this->guest($item), 1));
        $item->getVotes()->add(new Vote($item, $this->guest($item), -1));
        $item->recountVotes();

        self::assertSame(2, $item->getUpvotes());
        self::assertSame(1, $item->getDownvotes());
        self::assertSame(1, $item->getScore());
    }

    public function testLockedItemIsNoLongerVotable(): void
    {
        $item = $this->item();
        self::assertTrue($item->getStatus()->isVotable());

        $item->lock();

        self::assertSame(QueueItemStatus::Locked, $item->getStatus());
        self::assertFalse($item->getStatus()->isVotable());
        self::assertTrue($item->getStatus()->isUpcoming());
        self::assertNotNull($item->getLockedAt());
    }

    public function testUnlockPutsTheItemBackInTheQueue(): void
    {
        $item = $this->item();
        $item->lock();
        $item->unlock();

        self::assertSame(QueueItemStatus::Pending, $item->getStatus());
        self::assertNull($item->getLockedAt());
    }

    public function testPlayingThenPlayedKeepsTheFirstPlayedAt(): void
    {
        $item = $this->item();
        $item->startPlaying();
        $playedAt = $item->getPlayedAt();
        $item->markPlayed();

        self::assertSame(QueueItemStatus::Played, $item->getStatus());
        self::assertSame($playedAt, $item->getPlayedAt());
        self::assertFalse($item->getStatus()->isUpcoming());
    }

    private function item(): QueueItem
    {
        $event = new Event(new Host('host@example.test'), 'Soirée', 'ABCDEF');

        return new QueueItem($event, new Track('track1', 'Titre', 'Artiste'), null);
    }

    private function guest(QueueItem $item): Guest
    {
        return new Guest($item->getEvent(), 'Convive', bin2hex(random_bytes(8)));
    }
}
