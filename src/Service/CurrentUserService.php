<?php

namespace App\Service;

use App\Authentication\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

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

    public function setCurrentUser(User $user): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }
}
