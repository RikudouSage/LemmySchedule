<?php

namespace App\Job;

use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Uid\Uuid;

#[Deprecated]
final readonly class DeleteFileJob
{
    public function __construct(
        public Uuid $fileId,
    ) {
    }
}
