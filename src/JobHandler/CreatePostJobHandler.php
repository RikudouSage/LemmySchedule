<?php

namespace App\JobHandler;

use App\Authentication\User;
use App\FileProvider\FileProvider;
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
        private FileProvider $fileProvider,
    ) {
    }

    public function __invoke(CreatePostJob $job): void
    {
        $imageUrl = null;
        $api = $this->apiFactory->get($job->instance, jwt: $job->jwt);
        if ($imageId = $job->imageId) {
            $imageUrl = $this->fileProvider->getLink($imageId, new User('fake_user', $job->instance, $job->jwt));
        }
        $post = $api->post()->create(
            community: $job->community,
            name: $job->title,
            body: $job->text,
            language: $job->language,
            nsfw: $job->nsfw,
            url: $job->url ?? $imageUrl,
        );
        if ($job->pinToCommunity) {
            try {
                $api->post()->pin($post->post, PostFeatureType::Community);
            } catch (LemmyApiException) {
                // ignore it, user probably doesn't have the permission to do that
            }
        }
        if ($job->pinToInstance) {
            try {
                $api->post()->pin($post->post, PostFeatureType::Local);
            } catch (LemmyApiException) {
                // ignore it, user probably doesn't have the permission to do that
            }
        }
    }
}
