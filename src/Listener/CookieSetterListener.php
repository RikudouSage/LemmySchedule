<?php

namespace App\Listener;

use App\Service\CookieSetter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class CookieSetterListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly CookieSetter $cookieSetter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $cookies = $this->cookieSetter->getCookies();
        if (!count($cookies)) {
            return;
        }

        $response = $event->getResponse();
        foreach ($cookies as $cookie) {
            $response->headers->setCookie($cookie);
        }
        $event->setResponse($response);
    }
}
