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
use App\Service\TitleExpressionReplacer;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Rikudou\LemmyApi\Enum\ListingType;
use Rikudou\LemmyApi\Enum\PostFeatureType;
use Rikudou\LemmyApi\Enum\SortType;
use Rikudou\LemmyApi\Exception\LemmyApiException;
use Rikudou\LemmyApi\LemmyApi;
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
        private TitleExpressionReplacer $expressionReplacer,
    ) {
    }

    public function __invoke(CreatePostJob $job): void
    {
        $api = $this->apiFactory->get($job->instance, jwt: $job->jwt);
        if ($this->hasDuplicates($job, $api)) {
            return;
        }

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
        if ($imageId = $job->imageId) {
            $imageUrl = $chosenFileProvider->getLink($imageId, new User('fake_user', $job->instance, $job->jwt));
        }

        $targetTimezone = $job->timezoneName ?? 'UTC';
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set($targetTimezone);
        $title = $this->expressionReplacer->replace($job->title);
        date_default_timezone_set($originalTimezone);

        $post = $api->post()->create(
            community: $job->community,
            name: $title,
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

    private function hasDuplicates(CreatePostJob $job, LemmyApi $api): bool
    {
        if (!$job->checkForUrlDuplicates) {
            return false;
        }
        if (!$job->url) {
            return false;
        }

        $minDate = (new DateTimeImmutable())->sub(new DateInterval('P1D'));
        $page = 1;

        do {
            $posts = $api->post()->getPosts(
                community: $job->community,
                page: $page,
                sort: SortType::New,
                listingType: ListingType::All,
            );
            foreach ($posts as $post) {
                if ($post->post->published < $minDate) {
                    break 2;
                }

                if (!$post->post->url) {
                    continue;
                }

                if (trim($post->post->url, '/') === trim($job->url, '/')) {
                    return true;
                }
            }
            ++$page;
        } while (count($posts));

        return false;
    }
}
