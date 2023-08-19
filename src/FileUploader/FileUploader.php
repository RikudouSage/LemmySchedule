<?php

namespace App\FileUploader;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;

interface FileUploader
{
    public function upload(File $file): Uuid;

    public function delete(Uuid $fileId): void;

    public function get(Uuid $fileId): File;
}
