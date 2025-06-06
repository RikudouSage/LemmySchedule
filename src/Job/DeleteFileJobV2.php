<?php

namespace App\Job;

final readonly class DeleteFileJobV2
{
    public function __construct(
        public int $fileId,
    ) {
    }
}
