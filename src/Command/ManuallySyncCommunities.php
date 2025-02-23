<?php

namespace App\Command;

use App\Job\FetchCommunitiesJob;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

#[AsCommand('app:sync:manual')]
final class ManuallySyncCommunities extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('instance', InputArgument::REQUIRED)
            ->addOption('sync', mode: InputOption::VALUE_NONE)
            ->addOption('jwt', mode: InputOption::VALUE_REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $stamps = $input->getOption('sync') ? [new TransportNamesStamp('sync')] : [];

        $jwt = $input->getOption('jwt') ?? $io->askHidden('JWT');

        $this->messageBus->dispatch(new FetchCommunitiesJob(
            instance: $input->getArgument('instance'),
            jwt: $jwt,
        ), $stamps);

        return self::SUCCESS;
    }
}
