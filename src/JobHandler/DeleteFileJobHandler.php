<?php

namespace App\JobHandler;

use App\FileUploader\FileUploader;
use App\Job\DeleteFileJob;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteFileJobHandler
{
    public function __construct(
        private FileUploader $uploader,
    ) {
    }

    public function __invoke(DeleteFileJob $job): void
    {
        $this->uploader->delete($job->fileId);
    }
}
