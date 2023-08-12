<?php

namespace App\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class ProvideTotpException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'TOTP is missing.';
    }
}
