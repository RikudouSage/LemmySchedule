<?php

namespace App\Job;

final readonly class PinUnpinPostJob
{
    public function __construct(
        public int $postId,
        public string $jwt,
        public string $instance,
        public bool $pin,
    ) {
    }
}
