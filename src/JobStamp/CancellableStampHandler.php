<?php

namespace App\JobStamp;

use App\Service\JobManager;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class CancellableStampHandler implements MiddlewareInterface
{
    public function __construct(
        private JobManager $jobManager,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $cancellationStamp = $envelope->last(CancellableStamp::class);
        if ($cancellationStamp instanceof CancellableStamp) {
            if ($this->jobManager->isCancelled($cancellationStamp->jobId)) {
                return $envelope;
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
