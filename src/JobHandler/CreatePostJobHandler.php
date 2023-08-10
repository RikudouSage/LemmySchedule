<?php

namespace App\JobHandler;

use App\Job\CreatePostJob;
use App\Lemmy\LemmyApiFactory;
use Rikudou\LemmyApi\Enum\PostFeatureType;
use Rikudou\LemmyApi\Exception\LemmyApiException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreatePostJobHandler
{
    public function __construct(
        private LemmyApiFactory $apiFactory,
    ) {
    }

    public function __invoke(CreatePostJob $job): void
    {
        $api = $this->apiFactory->get($job->instance, jwt: $job->jwt);
        $post = $api->post()->create(
            community: $job->community,
            name: $job->title,
            body: $job->text,
            language: $job->language,
            nsfw: $job->nsfw,
            url: $job->url,
        );
        if ($job->pinToCommunity) {
            try {
                $api->post()->pin($post->post, PostFeatureType::Community);
            } catch (LemmyApiException) {
                // ignore it, user probably doesn't have the permission to do that
            }
        }
    }
}
