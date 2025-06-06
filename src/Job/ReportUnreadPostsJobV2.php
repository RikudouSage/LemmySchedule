<?php

namespace App\Job;

final readonly class ReportUnreadPostsJobV2
{
    public function __construct(
        public int $jobId,
    ) {
    }
}
