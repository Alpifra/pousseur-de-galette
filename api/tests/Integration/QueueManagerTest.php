<?php

namespace App\Tests\Integration;

use App\Entity\QueueItem;
use App\Enum\EventStatus;
use App\Enum\QueueItemStatus;
use App\Exception\DomainException;
use App\Repository\QueueItemRepository;
use App\Service\QueueManager;

class QueueManagerTest extends DomainTestCase
{
    private QueueManager $queue;
    private QueueItemRepository $items;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var QueueManager $queue */
        $queue = self::getContainer()->get(QueueManager::class);
        $this->queue = $queue;
        /** @var QueueItemRepository $items */
        $items = self::getContainer()->get(QueueItemRepository::class);
        $this->items = $items;
    }

    public function testProposingCountsAsAnUpvoteFromItsAuthor(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1', 'Djadja')]);
        $event = $this->createEvent();
        $guest = $this->createGuest($event, 'Marion');

        $result = $this->queue->propose($guest, 't1');

        self::assertFalse($result['alreadyQueued']);
        self::assertSame(1, $result['item']->getScore());
        self::assertSame(QueueItemStatus::Pending, $result['item']->getStatus());
        self::assertSame($guest, $result['item']->getProposedBy());
    }

    public function testProposingAnAlreadyQueuedTrackUpvotesIt(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1')]);
        $event = $this->createEvent();
        $first = $this->createGuest($event, 'Marion');
        $second = $this->createGuest($event, 'Thomas');

        $this->queue->propose($first, 't1');
        $result = $this->queue->propose($second, 't1');

        self::assertTrue($result['alreadyQueued']);
        self::assertSame(2, $result['item']->getScore());
        self::assertCount(1, $this->items->findRanked($event));
    }

    public function testTheGuestQuotaLimitsPendingProposals(): void
    {
        $this->fakeCatalog([
            't1' => $this->track('t1'),
            't2' => $this->track('t2'),
            't3' => $this->track('t3'),
        ]);
        $event = $this->createEvent();
        $event->getSettings()->maxPendingPerGuest = 2;
        $this->em->flush();
        $guest = $this->createGuest($event, 'Marion');

        $this->queue->propose($guest, 't1');
        $this->queue->propose($guest, 't2');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Tu as déjà 2 galettes en attente.');
        $this->queue->propose($guest, 't3');
    }

    public function testExplicitTracksCanBeBlocked(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1', 'Titre', 200_000, true)]);
        $event = $this->createEvent();
        $event->getSettings()->blockExplicit = true;
        $this->em->flush();
        $guest = $this->createGuest($event, 'Marion');

        $this->expectExceptionObject(DomainException::explicitBlocked());
        $this->queue->propose($guest, 't1');
    }

    public function testTooLongTracksAreRejected(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1', 'Symphonie', 900_000)]);
        $event = $this->createEvent();
        $guest = $this->createGuest($event, 'Marion');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Ce titre dépasse la durée maximale (10 min).');
        $this->queue->propose($guest, 't1');
    }

    public function testATrackJustPlayedCannotBeProposedAgain(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1')]);
        $event = $this->createEvent();
        $guest = $this->createGuest($event, 'Marion');

        $played = new QueueItem($event, $this->track('t1'), null);
        $played->markPlayed();
        $this->em->persist($played);
        $this->em->flush();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Ce titre vient de passer, patiente 120 min.');
        $this->queue->propose($guest, 't1');
    }

    public function testQueueIsOrderedByPinnedThenScoreThenAge(): void
    {
        $this->fakeCatalog([
            'old' => $this->track('old', 'Proposé en premier'),
            'loved' => $this->track('loved', 'Plébiscité'),
            'pinned' => $this->track('pinned', 'Choix de l\'hôte'),
        ]);
        $event = $this->createEvent();
        $author = $this->createGuest($event, 'Marion');
        $fan = $this->createGuest($event, 'Thomas');
        $otherFan = $this->createGuest($event, 'Chloé');

        $old = $this->queue->propose($author, 'old')['item'];
        $loved = $this->queue->propose($author, 'loved')['item'];
        $pinned = $this->queue->propose($author, 'pinned')['item'];

        $this->queue->vote($fan, $loved, 1);
        $this->queue->vote($otherFan, $loved, 1);
        $this->queue->pin($pinned, true);

        $ranked = array_map(
            static fn (QueueItem $item): string => $item->getTrack()->spotifyId,
            $this->items->findRanked($event),
        );

        self::assertSame(['pinned', 'loved', 'old'], $ranked);
        self::assertSame(3, $loved->getScore());
        self::assertSame(1, $old->getScore());
    }

    public function testDownvotesCanBeDisabled(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1')]);
        $event = $this->createEvent();
        $event->getSettings()->allowDownvotes = false;
        $this->em->flush();
        $author = $this->createGuest($event, 'Marion');
        $hater = $this->createGuest($event, 'Thomas');

        $item = $this->queue->propose($author, 't1')['item'];

        $this->expectExceptionObject(DomainException::downvotesDisabled());
        $this->queue->vote($hater, $item, -1);
    }

    public function testReachingTheRejectionThresholdRemovesTheTrack(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1')]);
        $event = $this->createEvent();
        $event->getSettings()->rejectionThreshold = -2;
        $this->em->flush();
        $author = $this->createGuest($event, 'Marion');
        $item = $this->queue->propose($author, 't1')['item'];

        foreach (['Thomas', 'Chloé', 'Yanis'] as $pseudo) {
            $this->queue->vote($this->createGuest($event, $pseudo), $item, -1);
        }

        self::assertSame(-2, $item->getScore());
        self::assertSame(QueueItemStatus::Rejected, $item->getStatus());
        self::assertSame([], $this->items->findRanked($event));
    }

    public function testALockedTrackCannotBeVotedOn(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1')]);
        $event = $this->createEvent();
        $author = $this->createGuest($event, 'Marion');
        $fan = $this->createGuest($event, 'Thomas');
        $item = $this->queue->propose($author, 't1')['item'];

        $item->lock();
        $this->em->flush();

        $this->expectExceptionObject(DomainException::itemLocked());
        $this->queue->vote($fan, $item, 1);
    }

    public function testAPausedEventAcceptsNothing(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1')]);
        $event = $this->createEvent(EventStatus::Paused);
        $guest = $this->createGuest($event, 'Marion');

        $this->expectExceptionObject(DomainException::eventNotLive());
        $this->queue->propose($guest, 't1');
    }

    public function testTheAuthorCannotVoteOnTheirOwnProposal(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1')]);
        $event = $this->createEvent();
        $author = $this->createGuest($event, 'Marion');
        $item = $this->queue->propose($author, 't1')['item'];

        $this->expectExceptionObject(DomainException::ownProposal());
        $this->queue->vote($author, $item, -1);
    }

    public function testProposingYourOwnTrackAgainChangesNothing(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1')]);
        $event = $this->createEvent();
        $author = $this->createGuest($event, 'Marion');
        $this->queue->propose($author, 't1');

        $result = $this->queue->propose($author, 't1');

        self::assertTrue($result['alreadyQueued']);
        self::assertSame(1, $result['item']->getScore());
    }

    public function testTheAuthorCanWithdrawTheirProposalAndGetTheirQuotaBack(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1'), 't2' => $this->track('t2')]);
        $event = $this->createEvent();
        $event->getSettings()->maxPendingPerGuest = 1;
        $this->em->flush();
        $author = $this->createGuest($event, 'Marion');
        $item = $this->queue->propose($author, 't1')['item'];

        $this->queue->withdraw($author, $item);

        self::assertSame(QueueItemStatus::Removed, $item->getStatus());
        self::assertSame(QueueItemStatus::Pending, $this->queue->propose($author, 't2')['item']->getStatus());
    }

    public function testOnlyTheAuthorCanWithdrawAProposal(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1')]);
        $event = $this->createEvent();
        $author = $this->createGuest($event, 'Marion');
        $item = $this->queue->propose($author, 't1')['item'];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Titre introuvable.');
        $this->queue->withdraw($this->createGuest($event, 'Thomas'), $item);
    }

    public function testALockedProposalCannotBeWithdrawn(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1')]);
        $event = $this->createEvent();
        $author = $this->createGuest($event, 'Marion');
        $item = $this->queue->propose($author, 't1')['item'];
        $item->lock();
        $this->em->flush();

        $this->expectExceptionObject(DomainException::itemLocked());
        $this->queue->withdraw($author, $item);
    }

    public function testAGuestCannotVoteOnAnotherEventQueue(): void
    {
        $this->fakeCatalog(['t1' => $this->track('t1')]);
        $event = $this->createEvent();
        $other = $this->createEvent();
        $author = $this->createGuest($event, 'Marion');
        $outsider = $this->createGuest($other, 'Intrus');
        $item = $this->queue->propose($author, 't1')['item'];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Titre introuvable.');
        $this->queue->vote($outsider, $item, 1);
    }
}
