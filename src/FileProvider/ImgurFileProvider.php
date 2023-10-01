<?php

namespace App\FileProvider;

use App\Authentication\User;
use App\FileUploader\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ImgurFileProvider implements FileProvider
{
    public function __construct(
        private FileUploader $uploader,
        private HttpClientInterface $httpClient,
        private string $accessToken,
    ) {
    }

    public function getLink(Uuid $fileId, User $user): ?string
    {
        $image = $this->uploader->get($fileId);

        $response = $this->httpClient->request(
            Request::METHOD_POST,
            'https://api.imgur.com/3/image',
            [
                'body' => [
                    'image' => base64_encode($image->getContent()),
                    'type' => 'base64',
                ],
                'auth_bearer' => $this->accessToken,
            ],
        );
        $body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->uploader->delete($fileId);

        return $body['data']['link'];
    }

    public function getDisplayName(): string
    {
        return 'Imgur';
    }

    public function getId(): string
    {
        return 'imgur';
    }

    public function isAvailable(): bool
    {
        return !!$this->accessToken;
    }

    public function isDefault(): bool
    {
        return false;
    }
}
