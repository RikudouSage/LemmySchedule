<?php

namespace App\JobHandler;

use App\Authentication\User;
use App\Job\PinUnpinPostJobV2;
use App\Job\PinUnpinPostJobV3;
use App\Lemmy\LemmyApiFactory;
use App\Service\CurrentUserService;
use App\Service\DatabaseMigrator;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[Deprecated]
#[AsMessageHandler]
final readonly class PinUnpinPostJobV2Handler
{
    public function __construct(
        private LemmyApiFactory        $apiFactory,
        private DatabaseMigrator       $databaseMigrator,
        private CurrentUserService     $currentUserService,
        private EntityManagerInterface $entityManager, private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(PinUnpinPostJobV2 $job): void
    {
        $api = $this->apiFactory->get($job->instance, jwt: $job->jwt);
        $me = $api->site()->getSite()->myUser?->localUserView->person;
        if ($me !== null) {
            $this->currentUserService->setCurrentUser(new User($me->name, $job->instance, $job->jwt));
        }
        $entity = $this->databaseMigrator->migrateJob(
            $this->currentUserService->getCurrentUser()?->getUserIdentifier() ?? 'unknown',
            $job,
        );
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->messageBus->dispatch(
            new PinUnpinPostJobV3($entity->getId()),
            [
                new DispatchAfterCurrentBusStamp(),
            ],
        );
    }
}
