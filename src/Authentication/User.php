<?php

namespace App\Authentication;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class User implements UserInterface
{
    public function __construct(
        private string $username,
        private string $instance,
        private string $jwt,
    ) {
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return "{$this->username}@{$this->instance}";
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getInstance(): string
    {
        return $this->instance;
    }

    public function getJwt(): string
    {
        return $this->jwt;
    }
}
