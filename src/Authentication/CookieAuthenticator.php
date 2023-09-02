<?php

namespace App\Authentication;

use App\Service\CookieSetter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CookieAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly CookieSetter $cookieSetter,
        private readonly string $defaultInstance,
        private readonly bool $singleInstanceMode,
        private readonly string $adminUsername,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->cookies->has(UserProvider::COOKIE_NAME);
    }

    public function authenticate(Request $request): Passport
    {
        $cookie = $request->cookies->get(UserProvider::COOKIE_NAME);
        assert(is_string($cookie));
        $value = json_decode($cookie, true, JSON_THROW_ON_ERROR);
        $instance = $value['instance'] ?? '';

        if ($this->singleInstanceMode && $instance !== $this->defaultInstance) {
            $this->cookieSetter->removeCookie(UserProvider::COOKIE_NAME);
            throw new CustomUserMessageAuthenticationException($this->translator->trans('Using this app you can only log in to the {instance} instance', [
                '{instance}' => $this->defaultInstance,
            ]));
        }
        if (!$value['username']) { // prevent user with no username being an admin
            $this->cookieSetter->removeCookie(UserProvider::COOKIE_NAME);
        }

        return new SelfValidatingPassport(
            new UserBadge(
                "{$value['username']}@{$value['instance']}",
                static fn () => new User($value['username'], $value['instance'], $value['jwt'], $value['username'] === $this->adminUsername),
            )
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        return new RedirectResponse($this->urlGenerator->generate('auth.login'));
    }
}
