<?php

namespace App\Listener;

use App\Authentication\UserProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final readonly class LogoutListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $response = $event->getResponse();
        $response->headers->clearCookie(UserProvider::COOKIE_NAME);
    }
}
