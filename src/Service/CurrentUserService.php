<?php

namespace App\Service;

use App\Authentication\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class CurrentUserService
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function getCurrentUser(): ?User
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user;
    }
}
