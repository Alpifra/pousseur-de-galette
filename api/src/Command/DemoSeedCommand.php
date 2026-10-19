<?php

namespace App\Command;

use App\Entity\Event;
use App\Entity\Guest;
use App\Entity\Host;
use App\Entity\QueueItem;
use App\Entity\Track;
use App\Entity\Vote;
use App\Enum\EventStatus;
use App\Repository\HostRepository;
use App\Service\GuestSession;
use App\Service\JoinCodeGenerator;
use App\Service\JoinLink;
use App\Service\QueueManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Seeds a playable soirée with fake tracks so the guest app can be tested without Spotify credentials. */
#[AsCommand(name: 'app:demo:seed', description: 'Crée une soirée de démonstration avec une file déjà remplie')]
class DemoSeedCommand extends Command
{
    private const HOST_EMAIL = 'demo@pousseur-de-galette.test';
    private const HOST_PASSWORD = 'galette';

    /** @var list<array{string, string, int}> */
    private const TRACKS = [
        ['Voyage voyage', 'Desireless', 251_000],
        ['Dernière danse', 'Indila', 213_000],
        ['Alors on danse', 'Stromae', 205_000],
        ['Je veux', 'Zaz', 215_000],
        ['Tous les mêmes', 'Stromae', 208_000],
        ['Balance ton quoi', 'Angèle', 158_000],
        ['Djadja', 'Aya Nakamura', 171_000],
        ['La Grenade', 'Clara Luciani', 202_000],
        ['Santa', 'Rihanna', 199_000],
        ['Mistral gagnant', 'Renaud', 224_000],
    ];

    /** @var list<string> */
    private const GUESTS = ['Marion', 'Thomas', 'Chloé', 'Yanis', 'Sofia', 'Hugo'];

    public function __construct(
        private HostRepository $hosts,
        private JoinCodeGenerator $joinCodes,
        private JoinLink $links,
        private UserPasswordHasherInterface $hasher,
        private QueueManager $queue,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $host = $this->hosts->findOneByEmail(self::HOST_EMAIL);
        if (null === $host) {
            $host = new Host(self::HOST_EMAIL);
            $host->setPassword($this->hasher->hashPassword($host, self::HOST_PASSWORD));
            $this->em->persist($host);
        }

        $event = new Event($host, 'Mariage de Léa & Tom', $this->joinCodes->generate());
        $event->setStatus(EventStatus::Live);
        $this->em->persist($event);

        $guests = [];
        foreach (self::GUESTS as $pseudo) {
            $guest = new Guest($event, $pseudo, GuestSession::createToken());
            $this->em->persist($guest);
            $guests[] = $guest;
        }

        [$playingName, $playingArtist, $playingDuration] = self::TRACKS[0];
        $playback = $event->getPlayback();
        $playback->isPlaying = true;
        $playback->fromQueue = true;
        $playback->trackId = 'demo000000000000000000';
        $playback->trackName = $playingName;
        $playback->trackArtist = $playingArtist;
        $playback->durationMs = $playingDuration;
        $playback->progressMs = 161_000;
        $playback->deviceName = 'Enceinte salle de réception';
        $playback->updatedAt = new \DateTimeImmutable();

        $queued = \array_slice(self::TRACKS, 1, 8);
        foreach ($queued as $index => [$name, $artist, $duration]) {
            $track = new Track(
                spotifyId: \sprintf('demo%018d', $index + 1),
                name: $name,
                artist: $artist,
                durationMs: $duration,
            );
            $proposer = $guests[$index % \count($guests)];
            $item = new QueueItem($event, $track, $proposer);
            $this->em->persist($item);
            $selfVote = new Vote($item, $proposer, 1);
            $this->em->persist($selfVote);
            $item->getVotes()->add($selfVote);

            // A spread of scores so the ranking is visible right away
            foreach ($guests as $position => $guest) {
                if ($guest === $proposer) {
                    continue;
                }
                $value = match (true) {
                    $position <= 4 - $index => 1,
                    6 === $index => -1,
                    default => 0,
                };
                if (0 !== $value) {
                    $vote = new Vote($item, $guest, $value);
                    $this->em->persist($vote);
                    $item->getVotes()->add($vote);
                }
            }

            $item->recountVotes();
        }

        $this->em->flush();
        $this->queue->broadcast($event);

        $io->success('Soirée de démonstration prête.');
        $io->definitionList(
            ['Code' => $event->getJoinCode()],
            ['Organisateur' => self::HOST_EMAIL.' / '.self::HOST_PASSWORD],
            ['URL' => $this->links->url($event)],
        );

        return Command::SUCCESS;
    }
}
