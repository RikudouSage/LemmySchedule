<?php

namespace App\FileUploader;

use App\Entity\StoredFile;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;

interface FileUploader
{
    public function upload(File $file): StoredFile;

    public function delete(Uuid|int $fileId): void;

    public function get(Uuid|int $fileId): File;
}
