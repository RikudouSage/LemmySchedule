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

    /**
     * @var array<string>
     */
    private array $cookiesToRemove = [];

    public function setCookie(Cookie $cookie): void
    {
        $this->cookies[$cookie->getName()] = $cookie;
    }

    public function removeCookie(string $name): void
    {
        $this->cookiesToRemove[] = $name;
    }

    /**
     * @return array<string, Cookie>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * @return array<string>
     */
    public function getCookiesToRemove(): array
    {
        return $this->cookiesToRemove;
    }
}
