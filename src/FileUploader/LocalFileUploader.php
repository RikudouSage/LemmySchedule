<?php

namespace App\FileUploader;

use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;

final readonly class LocalFileUploader implements FileUploader
{
    public function __construct(
        private string $uploadPath,
    ) {
    }

    public function upload(File $file): Uuid
    {
        $uuid = Uuid::v4();
        $content = $file->getContent();
        $targetPath = $this->getTargetPath($uuid);
        if (!is_dir(dirname($targetPath)) && !mkdir(dirname($targetPath), recursive: true)) {
            throw new RuntimeException('Failed to create a target directory: ' . dirname($targetPath));
        }

        file_put_contents($targetPath, $content) ?: throw new RuntimeException('Failed creating the target file');

        return $uuid;
    }

    public function delete(Uuid $fileId): void
    {
        $path = $this->getTargetPath($fileId);
        if (file_exists($path) && !unlink($path)) {
            throw new RuntimeException("Failed deleting the file at '{$path}'");
        }
    }

    public function get(Uuid $fileId): File
    {
        $path = $this->getTargetPath($fileId);
        if (!file_exists($path)) {
            throw new RuntimeException("The file at '{$path}' does not exist");
        }

        return new File($path);
    }

    private function getTargetPath(Uuid $id): string
    {
        return "{$this->uploadPath}/{$id}";
    }
}
