<?php

namespace App\FileProvider;

use App\Authentication\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Uid\Uuid;

#[AutoconfigureTag('app.file_provider')]
interface FileProvider
{
    public function getLink(Uuid|int $fileId, User $user): ?string;

    public function getDisplayName(): string;

    public function getId(): string;

    public function isAvailable(): bool;

    public function isDefault(): bool;
}
