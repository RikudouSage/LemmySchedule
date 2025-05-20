<?php

namespace App\JobStamp;

use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Uuid;

#[Deprecated]
final readonly class CancellableStamp implements StampInterface
{
    public function __construct(
        public Uuid $jobId,
    ) {
    }
}
