<?php

namespace App\JobHandler;

use App\Enum\PinType;
use App\Job\PinUnpinPostJobV3;
use App\Lemmy\LemmyApiFactory;
use App\Repository\PostPinUnpinStoredJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Rikudou\LemmyApi\Enum\PostFeatureType;
use Rikudou\LemmyApi\Exception\LemmyApiException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PinUnpinPostJobV3Handler
{
    public function __construct(
        private LemmyApiFactory $apiFactory,
        private PostPinUnpinStoredJobRepository $jobRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(PinUnpinPostJobV3 $message): void
    {
        $job = $this->jobRepository->find($message->jobId);
        if ($job === null) {
            return;
        }

        try {
            $api = $this->apiFactory->get($job->getInstance(), jwt: $job->getJwt());
            switch ($job->pin) {
                case PinType::PinToCommunity:
                    $api->post()->pin($job->getPostId(), PostFeatureType::Community);
                    break;
                case PinType::UnpinFromCommunity:
                    $api->post()->unpin($job->getPostId(), PostFeatureType::Community);
                    break;
                case PinType::PinToInstance:
                    $api->post()->pin($job->getPostId(), PostFeatureType::Local);
                    break;
                case PinType::UnpinFromInstance:
                    $api->post()->unpin($job->getPostId(), PostFeatureType::Local);
                    break;
            }
        } catch (LemmyApiException) {
            // ignore errors
        } finally {
            $this->entityManager->remove($job);
            $this->entityManager->flush();
        }
    }
}
