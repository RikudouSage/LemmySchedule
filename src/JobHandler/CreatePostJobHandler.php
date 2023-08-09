<?php

namespace App\JobHandler;

use App\Job\CreatePostJob;
use App\Lemmy\LemmyApiFactory;
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
        $api->post()->create(
            community: $job->community,
            name: $job->title,
            body: $job->text,
            language: $job->language,
            nsfw: $job->nsfw,
            url: $job->url,
        );
    }
}
