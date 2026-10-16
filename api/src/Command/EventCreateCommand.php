<?php

namespace App\Command;

use App\Enum\EventStatus;
use App\Repository\HostRepository;
use App\Service\EventManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:event:create', description: 'Crée une soirée et affiche son code de connexion')]
class EventCreateCommand extends Command
{
    public function __construct(
        private HostRepository $hosts,
        private EventManager $events,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('host', InputArgument::REQUIRED, 'Email de l\'organisateur')
            ->addArgument('name', InputArgument::REQUIRED, 'Nom de la soirée')
            ->addOption('live', null, InputOption::VALUE_NONE, 'Ouvre la soirée immédiatement');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $host = $this->hosts->findOneByEmail((string) $input->getArgument('host'));
        if (null === $host) {
            $io->error('Organisateur introuvable, crée-le avec app:host:create.');

            return Command::FAILURE;
        }

        $event = $this->events->create($host, (string) $input->getArgument('name'));
        if (true === $input->getOption('live')) {
            $this->events->changeStatus($event, EventStatus::Live);
        }

        $io->success(sprintf('Soirée « %s » créée (%s).', $event->getName(), $event->getStatus()->value));
        $io->definitionList(
            ['Code' => $event->getJoinCode()],
            ['Id' => (string) $event->getId()],
        );

        return Command::SUCCESS;
    }
}
