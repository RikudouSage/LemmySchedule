<?php

namespace App\Authentication;

use LogicException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class UserProvider implements UserProviderInterface
{
    public const COOKIE_NAME = 'user_auth';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, User::class, true);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new LogicException('Authenticator running in non-request context');
        }

        $cookie = $request->cookies->get(self::COOKIE_NAME);
        if ($cookie === null) {
            throw new UserNotFoundException('User not found');
        }
        assert(is_string($cookie));
        $json = json_decode($cookie, true, flags: JSON_THROW_ON_ERROR);
        assert(is_array($json));

        $user = new User(
            username: $json['username'],
            instance: $json['instance'],
            jwt: $json['jwt'],
        );
        if ($user->getUserIdentifier() !== $identifier) {
            throw new UserNotFoundException('User not found');
        }

        return $user;
    }
}
