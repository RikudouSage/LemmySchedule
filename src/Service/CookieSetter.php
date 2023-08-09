<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Used to set cookies from anywhere
 */
final class CookieSetter
{
    /**
     * @var array<string, Cookie>
     */
    private array $cookies = [];

    public function setCookie(Cookie $cookie): void
    {
        $this->cookies[$cookie->getName()] = $cookie;
    }

    /**
     * @return array<string, Cookie>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }
}
