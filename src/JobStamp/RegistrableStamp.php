<?php

namespace App\JobStamp;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Uuid;

final readonly class RegistrableStamp implements StampInterface
{
    public function __construct(
        public Uuid $jobId,
    ) {
    }
}
