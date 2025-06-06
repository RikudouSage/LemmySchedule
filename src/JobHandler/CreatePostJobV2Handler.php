<?php

namespace App\JobHandler;

use App\Authentication\User;
use App\Entity\Counter;
use App\Entity\CreatePostStoredJob;
use App\Entity\PostPinUnpinStoredJob;
use App\Enum\PinType;
use App\FileProvider\FileProvider;
use App\Job\CreatePostJobV2;
use App\Job\PinUnpinPostJobV3;
use App\Lemmy\LemmyApiFactory;
use App\Repository\CounterRepository;
use App\Repository\CreatePostStoredJobRepository;
use App\Service\CurrentUserService;
use App\Service\JobScheduler;
use App\Service\ScheduleExpressionParser;
use App\Service\TitleExpressionReplacer;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Rikudou\LemmyApi\Enum\ListingType;
use Rikudou\LemmyApi\Enum\PostFeatureType;
use Rikudou\LemmyApi\Enum\SortType;
use Rikudou\LemmyApi\Exception\LemmyApiException;
use Rikudou\LemmyApi\LemmyApi;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreatePostJobV2Handler
{
    /**
     * @param iterable<FileProvider> $fileProviders
     */
    public function __construct(
        private LemmyApiFactory $apiFactory,
        private ScheduleExpressionParser $scheduleExpressionParser,
        private CurrentUserService $currentUserService,
        #[TaggedIterator('app.file_provider')]
        private iterable $fileProviders,
        private TitleExpressionReplacer $expressionReplacer,
        private CounterRepository $countersRepository,
        private CreatePostStoredJobRepository $jobRepository,
        private JobScheduler $jobScheduler,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreatePostJobV2 $message): void
    {
        $job = $this->jobRepository->find($message->jobId);
        if ($job === null) {
            return;
        }

        try {
            $api = $this->apiFactory->get($job->getInstance(), jwt: $job->getJwt());
            if ($this->hasDuplicates($job, $api)) {
                return;
            }

            $me = $api->site()->getSite()->myUser?->localUserView->person;
            if ($me !== null) {
                $this->currentUserService->setCurrentUser(new User($me->name, $job->getInstance(), $job->getJwt()));
            }

            $default = null;
            $chosenFileProvider = null;
            foreach ($this->fileProviders as $fileProvider) {
                if ($fileProvider->isDefault()) {
                    $default = $fileProvider;
                }
                if ($fileProvider->getId() === $job->getFileProviderId() && $fileProvider->isAvailable()) {
                    $chosenFileProvider = $fileProvider;
                }
            }
            $chosenFileProvider ??= $default;
            assert($chosenFileProvider !== null);

            $imageUrl = null;
            if ($image = $job->getImage()) {
                $imageUrl = $chosenFileProvider->getLink($image->getId(), new User('fake_user', $job->getInstance(), $job->getJwt()));
            }

            $targetTimezone = $job->getTimezoneName();
            $originalTimezone = date_default_timezone_get();
            date_default_timezone_set($targetTimezone);
            $title = $this->expressionReplacer->replace($job->getTitle());
            date_default_timezone_set($originalTimezone);

            $post = $api->post()->create(
                community: $job->getCommunityId(),
                name: $title,
                body: $job->getText(),
                language: $job->getLanguage(),
                nsfw: $job->isNsfw(),
                url: $job->getUrl() ?? $imageUrl,
                customThumbnail: $job->getThumbnailUrl() ?? $imageUrl,
            );
            $this->handleCounters($job);
            if ($job->shouldPinToCommunity()) {
                try {
                    $api->post()->pin($post->post, PostFeatureType::Community);
                } catch (LemmyApiException) {
                    // ignore it, user probably doesn't have the permission to do that
                }
            }
            if ($job->shouldPinToInstance()) {
                try {
                    $api->post()->pin($post->post, PostFeatureType::Local);
                } catch (LemmyApiException) {
                    // ignore it, user probably doesn't have the permission to do that
                }
            }

            foreach ($job->getComments() as $comment) {
                $api->comment()->create(post: $post->post, content: $comment);
            }

            if ($expression = $job->getScheduleExpression()) {
                sleep(1);
                assert($job->getScheduleTimezone() !== null);
                $nextDate = $this->scheduleExpressionParser->getNextRunDate(
                    expression: $expression,
                    timeZone: new DateTimeZone($job->getScheduleTimezone()),
                );

                $this->jobScheduler->schedule($message, $nextDate);

                $job->setScheduledAt(DateTimeImmutable::createFromInterface($nextDate));
                $this->entityManager->persist($job);
                $this->entityManager->flush();
            }
            if ($unpinAt = $job->getUnpinAt()) {
                $me = $api->site()->getSite()->myUser?->localUserView->person;
                if ($me !== null) {
                    $this->currentUserService->setCurrentUser(new User($me->name, $job->getInstance(), $job->getJwt()));
                }

                $entity = (new PostPinUnpinStoredJob())
                    ->setPostId($post->post->id)
                    ->setJwt($job->getJwt())
                    ->setInstance($job->getInstance())
                    ->setPinType($job->shouldPinToCommunity() ? PinType::UnpinFromCommunity : PinType::UnpinFromInstance)
                    ->setUserId($job->getUserId())
                ;
                $this->entityManager->persist($entity);
                $this->entityManager->flush();

                $this->jobScheduler->schedule(new PinUnpinPostJobV3(
                    $entity->getId(),
                ), $unpinAt);
            }
        } finally {
            $this->entityManager->remove($job);
            $this->entityManager->flush();
        }
    }

    private function hasDuplicates(CreatePostStoredJob $job, LemmyApi $api): bool
    {
        if (!$job->shouldCheckForUrlDuplicates()) {
            return false;
        }
        if (!$job->getUrl()) {
            return false;
        }

        $minDate = (new DateTimeImmutable())->sub(new DateInterval('P1D'));
        $page = 1;

        do {
            $posts = $api->post()->getPosts(
                community: $job->getCommunityId(),
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

                if (trim($post->post->url, '/') === trim($job->getUrl(), '/')) {
                    return true;
                }
            }
            ++$page;
        } while (count($posts));

        return false;
    }

    private function handleCounters(CreatePostStoredJob $job): void
    {
        $regex = '@#\[Counter\([\'"]([^\'"]+)[\'"]\)]#@';
        $result = $this->expressionReplacer->parse($job->getTitle());
        $counters = [];
        foreach ($result->validExpressions as $expression) {
            if (!preg_match($regex, $expression, $matches)) {
                continue;
            }

            $counters[] = $matches[1];
        }

        foreach ($counters as $counterName) {
            $counter = $this->countersRepository->findOneBy(['name' => $counterName, 'userId' => $job->getUserId()]);
            $counter ??= (new Counter())
                ->setName($counterName)
                ->setValue(0)
                ->setIncrementBy(1)
                ->setUserId($job->getUserId())
            ;
            $counter->setValue($counter->getValue() + $counter->getIncrementBy());
            $this->entityManager->persist($counter);
        }
        $this->entityManager->flush();
    }
}
