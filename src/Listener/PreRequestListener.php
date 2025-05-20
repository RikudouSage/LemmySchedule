<?php

namespace App\Listener;

use App\Service\DatabaseMigrator;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER_ARGUMENTS, method: 'onRequest')]
final readonly class PreRequestListener
{
    public function __construct(
        private DatabaseMigrator $databaseMigrator
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function onRequest(ControllerArgumentsEvent $event): void
    {
        $this->databaseMigrator->migrate();
    }
}
