<?php

namespace App\FileUploader;

use App\Entity\StoredFile;
use App\Repository\StoredFileRepository;
use App\Service\ImageMetadataRemover;
use App\Service\TemporaryFileCleaner;
use AsyncAws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;

final readonly class S3FileUploader implements FileUploader
{
    public function __construct(
        private S3Client             $s3client,
        private string               $bucket,
        private TemporaryFileCleaner $fileCleaner,
        private ImageMetadataRemover $metadataRemover,
        private StoredFileRepository $storedFileRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function upload(File $file): StoredFile
    {
        $this->metadataRemover->stripMetadata($file);
        $uuid = Uuid::v4();
        $this->s3client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $uuid,
            'Body' => $file->getContent(),
            'ContentType' => $file->getMimeType(),
        ]);

        return (new StoredFile())
            ->setPath($uuid);
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

        $this->s3client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $fileId,
        ]);
    }

    public function get(Uuid|int $fileId): File
    {
        if (is_int($fileId)) {
            $entity = $this->storedFileRepository->find($fileId);
            if ($entity === null) {
                throw new RuntimeException("The file with ID '{$fileId}' does not exist");
            }

            $fileId = $entity->getPath();
        }

        $result = $this->s3client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $fileId,
        ]);

        $temporaryFile = tempnam(sys_get_temp_dir(), $fileId) ?: throw new RuntimeException('Failed creating a temporary file');
        $temporaryFileStream = fopen($temporaryFile, 'w');
        stream_copy_to_stream($result->getBody()->getContentAsResource(), $temporaryFileStream);
        fclose($temporaryFileStream);

        $this->fileCleaner->cleanFileOnShutdown($temporaryFile);

        return new File($temporaryFile);
    }
}
