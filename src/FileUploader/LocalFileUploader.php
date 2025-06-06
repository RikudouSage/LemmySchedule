<?php

namespace App\FileUploader;

use App\Entity\StoredFile;
use App\Repository\StoredFileRepository;
use App\Service\ImageMetadataRemover;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;

final readonly class LocalFileUploader implements FileUploader
{
    public function __construct(
        private string $uploadPath,
        private ImageMetadataRemover $metadataRemover,
        private StoredFileRepository $storedFileRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function upload(File $file): StoredFile
    {
        $uuid = Uuid::v4();
        $this->metadataRemover->stripMetadata($file);
        $content = $file->getContent();

        $targetPath = $this->getTargetPath($uuid);
        if (!is_dir(dirname($targetPath)) && !mkdir(dirname($targetPath), recursive: true)) {
            throw new RuntimeException('Failed to create a target directory: ' . dirname($targetPath));
        }

        file_put_contents($targetPath, $content) ?: throw new RuntimeException('Failed creating the target file');

        return (new StoredFile())
            ->setPath(substr($targetPath, strlen($this->uploadPath)));
    }

    public function delete(Uuid|int $fileId): void
    {
        if (is_int($fileId)) {
            $entity = $this->storedFileRepository->find($fileId);
            if ($entity === null) {
                return;
            }

            $fileId = $entity->getPath();
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }

        $path = $this->getTargetPath($fileId);
        if (file_exists($path) && !unlink($path)) {
            throw new RuntimeException("Failed deleting the file at '{$path}'");
        }
    }

    public function get(Uuid|int $fileId): File
    {
        if (is_int($fileId)) {
            $entity = $this->storedFileRepository->find($fileId);
            if ($entity === null) {
                throw new RuntimeException("The file with ID '{$fileId}' does not exist");
            }

            $path = $this->getTargetPath($entity->getPath());
        } else {
            $path = $this->getTargetPath($fileId);
        }

        if (!file_exists($path)) {
            throw new RuntimeException("The file at '{$path}' does not exist");
        }

        return new File($path);
    }

    private function getTargetPath(string $path): string
    {
        return "{$this->uploadPath}/{$path}";
    }
}
