<?php

namespace App\Command;

use App\Repository\EventRepository;
use App\Service\PlaybackSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:playback:run',
    description: 'Suit la lecture Spotify des soirées en cours et alimente la file juste à temps',
)]
class PlaybackRunCommand extends Command
{
    private bool $stopped = false;

    public function __construct(
        private EventRepository $events,
        private PlaybackSynchronizer $synchronizer,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('once', null, InputOption::VALUE_NONE, 'Un seul passage puis sortie')
            ->addOption('interval', 'i', InputOption::VALUE_REQUIRED, 'Secondes entre deux passages', '4')
            ->addOption('duration', 'd', InputOption::VALUE_REQUIRED, 'Durée de vie du processus en secondes (0 = illimité)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $interval = max(1, (int) $input->getOption('interval'));
        $duration = max(0, (int) $input->getOption('duration'));
        $deadline = 0 === $duration ? null : time() + $duration;

        $this->listenForSignals();

        do {
            $events = $this->events->findPlayable();
            foreach ($events as $event) {
                try {
                    $this->synchronizer->tick($event);
                } catch (\Throwable $e) {
                    $this->logger->error('Playback tick failed', [
                        'event' => (string) $event->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    $io->warning(\sprintf('%s : %s', $event->getName(), $e->getMessage()));
                }
            }

            if ($output->isVerbose()) {
                $io->writeln(\sprintf('[%s] %d soirée(s) suivie(s)', date('H:i:s'), \count($events)));
            }

            // Long-running process: never keep entities from the previous pass
            $this->em->clear();

            if (true === $input->getOption('once') || $this->stopped) {
                break;
            }

            sleep($interval);
        } while (null === $deadline || time() < $deadline);

        return Command::SUCCESS;
    }

    private function listenForSignals(): void
    {
        if (!\function_exists('pcntl_signal')) {
            return;
        }

        $stop = function (): void { $this->stopped = true; };
        pcntl_async_signals(true);
        pcntl_signal(\SIGTERM, $stop);
        pcntl_signal(\SIGINT, $stop);
    }
}
