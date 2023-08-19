<?php

namespace App\FileUploader;

use App\Service\TemporaryFileCleaner;
use AsyncAws\S3\S3Client;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;

final readonly class S3FileUploader implements FileUploader
{
    public function __construct(
        private S3Client $s3client,
        private string $bucket,
        private TemporaryFileCleaner $fileCleaner,
    ) {
    }

    public function upload(File $file): Uuid
    {
        $uuid = Uuid::v4();
        $this->s3client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $uuid,
            'Body' => $file->getContent(),
            'ContentType' => $file->getMimeType(),
        ]);

        return $uuid;
    }

    public function delete(Uuid $fileId): void
    {
        $this->s3client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $fileId,
        ]);
    }

    public function get(Uuid $fileId): File
    {
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
