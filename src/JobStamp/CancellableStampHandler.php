<?php

namespace App\JobStamp;

use App\FileUploader\FileUploader;
use App\Job\CreatePostJob;
use App\Service\JobManager;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class CancellableStampHandler implements MiddlewareInterface
{
    public function __construct(
        private JobManager $jobManager,
        private FileUploader $fileUploader,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $cancellationStamp = $envelope->last(CancellableStamp::class);
        if ($cancellationStamp instanceof CancellableStamp) {
            if ($this->jobManager->isCancelled($cancellationStamp->jobId)) {
                $message = $envelope->getMessage();
                if ($message instanceof CreatePostJob && $message->imageId) {
                    $this->fileUploader->delete($message->imageId);
                }

                return $envelope;
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
