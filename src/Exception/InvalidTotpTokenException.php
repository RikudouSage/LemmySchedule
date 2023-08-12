<?php

namespace App\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class InvalidTotpTokenException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'The TOTP token is invalid.';
    }
}
