<?php

namespace App\Job;

final readonly class FetchCommunitiesJob
{
    public function __construct(
        public string $instance,
        public string $jwt,
    ) {
    }
}
