<?php

namespace App\JobHandler;

use App\Enum\PinType;
use App\Job\PinUnpinPostJobV2;
use App\Lemmy\LemmyApiFactory;
use Rikudou\LemmyApi\Enum\PostFeatureType;
use Rikudou\LemmyApi\Exception\LemmyApiException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PinUnpinPostJobV2Handler
{
    public function __construct(
        private LemmyApiFactory $apiFactory,
    ) {
    }

    public function __invoke(PinUnpinPostJobV2 $job): void
    {
        $api = $this->apiFactory->get($job->instance, jwt: $job->jwt);

        try {
            switch ($job->pin) {
                case PinType::PinToCommunity:
                    $api->post()->pin($job->postId, PostFeatureType::Community);
                    break;
                case PinType::UnpinFromCommunity:
                    $api->post()->unpin($job->postId, PostFeatureType::Community);
                    break;
                case PinType::PinToInstance:
                    $api->post()->pin($job->postId, PostFeatureType::Local);
                    break;
                case PinType::UnpinFromInstance:
                    $api->post()->unpin($job->postId, PostFeatureType::Local);
                    break;
            }
        } catch (LemmyApiException) {
            // ignore errors
        }
    }
}
