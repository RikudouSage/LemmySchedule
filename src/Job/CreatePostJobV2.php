<?php

namespace App\Job;

final readonly class CreatePostJobV2
{
    public function __construct(
        public int $jobId,
    ) {
    }
}
