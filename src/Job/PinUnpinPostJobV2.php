<?php

namespace App\Job;

use App\Enum\PinType;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
final class PinUnpinPostJobV2
{
    public function __construct(
        public int $postId,
        public string $jwt,
        public string $instance,
        public PinType $pin,
    ) {
    }
}
