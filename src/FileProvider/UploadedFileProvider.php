<?php

namespace App\FileProvider;

use App\Authentication\User;
use App\FileUploader\FileUploader;
use App\Lemmy\LemmyApiFactory;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class UploadedFileProvider implements FileProvider
{
    public function __construct(
        private FileUploader $uploader,
        private LemmyApiFactory $apiFactory,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLink(Uuid|int $fileId, User $user): ?string
    {
        $api = $this->apiFactory->getForUser($user);

        $image = $this->uploader->get($fileId);
        $result = $api->miscellaneous()->uploadImage($image);
        if (!$result->success) {
            return null;
        }

        return "https://{$user->getInstance()}/pictrs/image/{$result->files[0]->file}";
    }

    public function getDisplayName(): string
    {
        return $this->translator->trans('My instance');
    }

    public function getId(): string
    {
        return 'instance';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isDefault(): bool
    {
        return true;
    }
}
