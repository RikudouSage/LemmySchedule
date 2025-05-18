<?php

namespace App\Service;

use DateTimeInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final readonly class JobScheduler
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function schedule(object $job, DateTimeInterface $dateTime): void
    {
        $delayStamp = DelayStamp::delayUntil($dateTime);
        $this->messageBus->dispatch($job, [$delayStamp]);
    }
}
