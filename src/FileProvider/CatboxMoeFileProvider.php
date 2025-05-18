<?php

namespace App\FileProvider;

use App\Authentication\User;
use App\FileUploader\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CatboxMoeFileProvider implements FileProvider
{
    public function __construct(
        private FileUploader $uploader,
        private HttpClientInterface $httpClient,
        private string $userHash,
        private bool $allowAnonymous,
        private MimeTypeGuesserInterface $mimeTypeGuesser,
        private MimeTypesInterface $mimeTypes,
    ) {
    }

    public function getLink(Uuid|int $fileId, User $user): ?string
    {
        $image = $this->uploader->get($fileId);
        $mimeType = $this->mimeTypeGuesser->guessMimeType($image->getRealPath());
        $extension = $this->mimeTypes->getExtensions($mimeType)[0] ?? null;

        $tempFile = sys_get_temp_dir() . '/' . $fileId . '.' . $extension;
        try {
            copy($image->getRealPath(), $tempFile);

            $body = [
                'reqtype' => 'fileupload',
                'fileToUpload' => new DataPart(new File($tempFile, pathinfo($tempFile, PATHINFO_BASENAME))),
            ];
            if ($this->userHash) {
                $body['userhash'] = $this->userHash;
            }
            $body = new FormDataPart($body);

            $response = $this->httpClient->request(
                Request::METHOD_POST,
                'https://catbox.moe/user/api.php',
                [
                    'body' => $body->bodyToIterable(),
                    'headers' => $body->getPreparedHeaders()->toArray(),
                ],
            );

            return $response->getContent();
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function getDisplayName(): string
    {
        return 'Catbox.moe';
    }

    public function getId(): string
    {
        return 'catbox_moe';
    }

    public function isAvailable(): bool
    {
        return $this->allowAnonymous || $this->userHash;
    }

    public function isDefault(): bool
    {
        return false;
    }
}
