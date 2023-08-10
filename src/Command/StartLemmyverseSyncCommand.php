<?php

namespace App\Command;

use App\Job\RefreshInstanceListJob;
use App\Service\JobManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand('app:sync:lemmyverse')]
final class StartLemmyverseSyncCommand extends Command
{
    public function __construct(
        private readonly JobManager $jobManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobId = Uuid::v4();

        $io = new SymfonyStyle($input, $output);
        $this->jobManager->createJob(new RefreshInstanceListJob(), null);
        $io->success('Success!');

        return self::SUCCESS;
    }
}
