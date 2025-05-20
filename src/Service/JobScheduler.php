<?php

namespace App\Service;

use DateTimeInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class JobScheduler
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function schedule(object $job, DateTimeInterface $dateTime): void
    {
        /** @var array<StampInterface> $stamps */
        $stamps = [];
        $delayStamp = DelayStamp::delayUntil($dateTime);
        if ($delayStamp->getDelay() > 0) {
            $stamps[] = $delayStamp;
        }

        $this->messageBus->dispatch($job, $stamps);
    }
}
