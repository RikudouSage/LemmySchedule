<?php

namespace App\Job;

final readonly class PinUnpinPostJobV3
{
    public function __construct(
        public int $jobId,
    ) {
    }
}
