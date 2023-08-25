<?php

namespace App\FileProvider;

use App\Authentication\User;
use Symfony\Component\Uid\Uuid;

interface FileProvider
{
    public function getLink(Uuid $fileId, User $user): ?string;
}
