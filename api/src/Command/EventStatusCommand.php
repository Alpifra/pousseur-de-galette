<?php

namespace App\Command;

use App\Enum\EventStatus;
use App\Repository\EventRepository;
use App\Service\EventManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:event:status', description: 'Change le statut d\'une soirée (draft, live, paused, ended)')]
class EventStatusCommand extends Command
{
    public function __construct(
        private EventRepository $events,
        private EventManager $manager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('code', InputArgument::REQUIRED, 'Code de la soirée')
            ->addArgument('status', InputArgument::REQUIRED, implode(' | ', array_column(EventStatus::cases(), 'value')));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $event = $this->events->findOneByJoinCode((string) $input->getArgument('code'));
        if (null === $event) {
            $io->error('Soirée introuvable.');

            return Command::FAILURE;
        }

        $status = EventStatus::tryFrom((string) $input->getArgument('status'));
        if (null === $status) {
            $io->error('Statut inconnu.');

            return Command::FAILURE;
        }

        $this->manager->changeStatus($event, $status);
        $io->success(sprintf('« %s » est maintenant %s.', $event->getName(), $status->value));

        return Command::SUCCESS;
    }
}
