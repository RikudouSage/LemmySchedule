<?php

namespace App\FileProvider;

use App\Authentication\User;
use App\FileUploader\FileUploader;
use App\Lemmy\LemmyApiFactory;
use Symfony\Component\Uid\Uuid;

final readonly class UploadedFileProvider implements FileProvider
{
    public function __construct(
        private FileUploader $uploader,
        private LemmyApiFactory $apiFactory,
    ) {
    }

    public function getLink(Uuid $fileId, User $user): ?string
    {
        $api = $this->apiFactory->getForUser($user);

        $image = $this->uploader->get($fileId);
        $result = $api->miscellaneous()->uploadImage($image);
        if (!$result->success) {
            return null;
        }
        $this->uploader->delete($fileId);

        return "https://{$user->getInstance()}/pictrs/image/{$result->files[0]->file}";
    }
}
