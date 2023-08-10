<?php

namespace App\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

#[AsCommand('app:run-sync')]
final class RunJobSynchronously extends Command
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'serialized-job',
                mode: InputArgument::REQUIRED,
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $job = $input->getArgument('serialized-job');
        assert(is_string($job));
        $job = base64_decode($job);
        if (!is_string($job)) {
            throw new RuntimeException('Failed base64 decoding the job');
        }
        $job = unserialize($job);
        if (!is_array($job) || !isset($job['body'])) {
            throw new RuntimeException('Invalid job - must be an array with a body key');
        }
        $job = $this->serializer->decode($job);
        $this->messageBus->dispatch($job, [
            new TransportNamesStamp(['sync']),
        ]);

        return self::SUCCESS;
    }
}
