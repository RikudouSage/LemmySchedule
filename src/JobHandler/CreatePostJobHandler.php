<?php

namespace App\JobHandler;

use App\Authentication\User;
use App\Enum\PinType;
use App\FileProvider\FileProvider;
use App\Job\CreatePostJob;
use App\Job\PinUnpinPostJobV2;
use App\Lemmy\LemmyApiFactory;
use App\Service\CurrentUserService;
use App\Service\JobManager;
use App\Service\ScheduleExpressionParser;
use DateTimeZone;
use Rikudou\LemmyApi\Enum\PostFeatureType;
use Rikudou\LemmyApi\Exception\LemmyApiException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreatePostJobHandler
{
    /**
     * @param iterable<FileProvider> $fileProviders
     */
    public function __construct(
        private LemmyApiFactory $apiFactory,
        private ScheduleExpressionParser $scheduleExpressionParser,
        private JobManager $jobManager,
        private CurrentUserService $currentUserService,
        #[TaggedIterator('app.file_provider')]
        private iterable $fileProviders,
    ) {
    }

    public function __invoke(CreatePostJob $job): void
    {
        $default = null;
        $chosenFileProvider = null;
        foreach ($this->fileProviders as $fileProvider) {
            if ($fileProvider->isDefault()) {
                $default = $fileProvider;
            }
            if ($fileProvider->getId() === $job->fileProvider && $fileProvider->isAvailable()) {
                $chosenFileProvider = $fileProvider;
            }
        }
        $chosenFileProvider ??= $default;
        assert($chosenFileProvider !== null);

        $imageUrl = null;
        $api = $this->apiFactory->get($job->instance, jwt: $job->jwt);
        if ($imageId = $job->imageId) {
            $imageUrl = $chosenFileProvider->getLink($imageId, new User('fake_user', $job->instance, $job->jwt, false));
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

        if ($expression = $job->scheduleExpression) {
            sleep(1);
            assert($job->scheduleTimezone !== null);
            $me = $api->site()->getSite()->myUser?->localUserView->person;
            if ($me !== null) {
                $this->currentUserService->setCurrentUser(new User($me->name, $job->instance, $job->jwt));
            }
            $nextDate = $this->scheduleExpressionParser->getNextRunDate(
                expression: $expression,
                timeZone: new DateTimeZone($job->scheduleTimezone),
            );
            $this->jobManager->createJob($job, $nextDate);
        }
        if ($unpinAt = $job->unpinAt) {
            $me = $api->site()->getSite()->myUser?->localUserView->person;
            if ($me !== null) {
                $this->currentUserService->setCurrentUser(new User($me->name, $job->instance, $job->jwt));
            }
            $this->jobManager->createJob(new PinUnpinPostJobV2(
                postId: $post->post->id,
                jwt: $job->jwt,
                instance: $job->instance,
                pin: $job->pinToCommunity ? PinType::UnpinFromCommunity : PinType::UnpinFromInstance,
            ), $unpinAt);
        }
    }
}
