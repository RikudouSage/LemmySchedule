<?php

namespace App\JobStamp;

use App\Service\JobManager;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final readonly class RegistrableStampHandler implements MiddlewareInterface
{
    public function __construct(
        private JobManager $jobManager,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $registrableStamp = $envelope->last(RegistrableStamp::class);
        if ($registrableStamp instanceof RegistrableStamp) {
            $expiresAt = (new DateTimeImmutable())->add(new DateInterval('PT1M'));
            $delayStamp = $envelope->last(DelayStamp::class);
            if ($delayStamp !== null) {
                $delayFor = $delayStamp->getDelay() / 1_000 + 10;
                $expiresAt = (new DateTimeImmutable())->add(new DateInterval("PT{$delayFor}S"));
            }
            if (!$this->jobManager->getJob($registrableStamp->jobId)) {
                $this->jobManager->registerJob($registrableStamp->jobId, $envelope->getMessage(), $expiresAt);
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
