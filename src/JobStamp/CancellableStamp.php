<?php

namespace App\JobStamp;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CancellableStamp implements StampInterface
{
    public function __construct(
        public Uuid $jobId,
    ) {
    }
}
