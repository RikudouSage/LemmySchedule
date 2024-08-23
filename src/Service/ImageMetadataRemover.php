<?php

namespace App\Service;

use Imagick;
use Symfony\Component\HttpFoundation\File\File;

final readonly class ImageMetadataRemover
{
    public function stripMetadata(File $file): void
    {
        $image = new Imagick($file->getRealPath());
        $profiles = $image->getImageProfiles('icc');
        $image->stripImage();
        if (count($profiles)) {
            $image->profileImage('icc', $profiles['icc']);
        }

        $image->writeImage();
        $image->clear();
        $image->destroy();
    }
}
