<?php

namespace App\JobHandler;

use App\Job\PinUnpinPostJob;
use App\Lemmy\LemmyApiFactory;
use Rikudou\LemmyApi\Enum\PostFeatureType;
use Rikudou\LemmyApi\Exception\LemmyApiException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PinUnpinPostJobHandler
{
    public function __construct(
        private LemmyApiFactory $apiFactory,
    ) {
    }

    public function __invoke(PinUnpinPostJob $job): void
    {
        $api = $this->apiFactory->get($job->instance, jwt: $job->jwt);

        try {
            if ($job->pin) {
                $api->post()->pin($job->postId, PostFeatureType::Community);
            } else {
                $api->post()->unpin($job->postId, PostFeatureType::Community);
            }
        } catch (LemmyApiException) {
            // ignore errors
        }
    }
}
