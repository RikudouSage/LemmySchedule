<?php

namespace App\Job;

use Symfony\Component\Uid\Uuid;

final readonly class DeleteFileJob
{
    public function __construct(
        public Uuid $fileId,
    ) {
    }
}
