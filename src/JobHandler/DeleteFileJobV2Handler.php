<?php

namespace App\JobHandler;

use App\FileUploader\FileUploader;
use App\Job\DeleteFileJobV2;
use App\Repository\StoredFileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteFileJobV2Handler
{
    public function __construct(
        private FileUploader         $uploader,
        private StoredFileRepository $fileRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(DeleteFileJobV2 $job): void
    {
        $entity = $this->fileRepository->find($job->fileId);
        if ($entity === null) {
            return;
        }
        try {
            $this->uploader->delete($job->fileId);
        } finally {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
