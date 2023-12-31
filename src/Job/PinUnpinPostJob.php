<?php

namespace App\Job;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
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
