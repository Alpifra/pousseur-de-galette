<?php

namespace App\Command;

use App\Entity\Host;
use App\Repository\HostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:host:create', description: 'Crée un compte organisateur')]
class HostCreateCommand extends Command
{
    public function __construct(
        private HostRepository $hosts,
        private UserPasswordHasherInterface $hasher,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::OPTIONAL, 'Généré si omis');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        if (null !== $this->hosts->findOneByEmail($email)) {
            $io->error(sprintf('%s existe déjà.', $email));

            return Command::FAILURE;
        }

        $password = $input->getArgument('password');
        $password = is_string($password) && '' !== $password ? $password : bin2hex(random_bytes(6));

        $host = new Host($email);
        $host->setPassword($this->hasher->hashPassword($host, $password));
        $this->em->persist($host);
        $this->em->flush();

        $io->success(sprintf('Organisateur %s créé.', $email));
        $io->writeln(sprintf('Mot de passe : <info>%s</info>', $password));

        return Command::SUCCESS;
    }
}
