<?php

namespace App\Authentication;

use App\Exception\InvalidTotpTokenException;
use App\Exception\ProvideTotpException;
use App\Job\FetchCommunitiesJob;
use App\JobStamp\CancellableStamp;
use App\JobStamp\RegistrableStamp;
use App\Lemmy\LemmyApiFactory;
use App\Service\CookieSetter;
use DateTimeImmutable;
use Rikudou\LemmyApi\Exception\IncorrectPasswordException;
use Rikudou\LemmyApi\Exception\IncorrectTotpToken;
use Rikudou\LemmyApi\Exception\MissingTotpToken;
use Rikudou\LemmyApi\Exception\UserNotFoundException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;
use DateInterval;

final class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CookieSetter $cookieSetter,
        private readonly LemmyApiFactory $apiFactory,
        private readonly TranslatorInterface $translator,
        private readonly MessageBusInterface $messageBus,
        private readonly string $defaultInstance,
        private readonly bool $singleInstanceMode,
        private readonly string $adminUsername,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $instance = $request->request->get('instance');
        $username = $request->request->get('username');
        $password = $request->request->get('password');
        $totp = $request->request->get('totp');

        if ($this->singleInstanceMode && $instance !== $this->defaultInstance) {
            throw new CustomUserMessageAuthenticationException($this->translator->trans('Using this app you can only log in to the {instance} instance', [
                '{instance}' => $this->defaultInstance,
            ]));
        }

        $request->getSession()->set('last_instance', $instance);
        $request->getSession()->set(Security::LAST_USERNAME, $username);

        if (!$instance || !$username || !$password) {
            throw new CustomUserMessageAuthenticationException($this->translator->trans('All fields must be filled.'));
        }

        try {
            $api = $this->apiFactory->get(
                instance: $instance,
                username: $username,
                password: $password,
                totpToken: $totp,
            );
        } catch (IncorrectPasswordException|UserNotFoundException) {
            throw new CustomUserMessageAuthenticationException($this->translator->trans('The username, instance or password is invalid'));
        } catch (MissingTotpToken) {
            $request->getSession()->set('last_password', $password);
            throw new ProvideTotpException();
        } catch (IncorrectTotpToken) {
            $request->getSession()->set('last_password', $password);
            throw new InvalidTotpTokenException();
        }

        $cookieValue = [
            'username' => $username,
            'instance' => $instance,
            'jwt' => $api->getJwt(),
        ];
        $cookie = Cookie::create(
            name: UserProvider::COOKIE_NAME,
            value: json_encode($cookieValue),
            expire: (new DateTimeImmutable())->add(new DateInterval('P7D')),
        );
        $this->cookieSetter->setCookie($cookie);

        $jobId = Uuid::v4();
        $this->messageBus->dispatch(new FetchCommunitiesJob(instance: $instance, jwt: $cookieValue['jwt']), [
            new CancellableStamp($jobId),
            new RegistrableStamp($jobId),
        ]);

        return new SelfValidatingPassport(
            new UserBadge("{$username}@{$instance}", fn () => new User($username, $instance, $api->getJwt(), $username === $this->adminUsername)),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app.home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('auth.login');
    }
}
