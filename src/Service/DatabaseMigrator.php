<?php

namespace App\Service;

use App\Dto\CounterConfiguration;
use App\Entity\CommunityGroup;
use App\Entity\Counter;
use App\Entity\CreatePostStoredJob;
use App\Entity\PostPinUnpinStoredJob;
use App\Entity\UnreadPostReportStoredJob;
use App\FileUploader\FileUploader;
use App\Job\CreatePostJob;
use App\Job\PinUnpinPostJobV2;
use App\Job\ReportUnreadPostsJob;
use App\JobStamp\MetadataStamp;
use App\Service\CountersRepository as OldCountersRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JetBrains\PhpStorm\Language;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Rikudou\LemmyApi\Response\Model\Community;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Uid\Uuid;

final readonly class DatabaseMigrator
{
    public function __construct(
        private JobManager $jobManager,
        private CacheItemPoolInterface $cache,
        private EntityManagerInterface $entityManager,
        private FileUploader $fileUploader,
        private CommunityGroupManager $groupManager,
        private OldCountersRepository $countersRepository,
    ) {
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function migrate(): void
    {
        $this->cache->deleteItem('migration_v2_succeeded');
        $cacheItem = $this->cache->getItem('migration_v2_succeeded');
        if ($cacheItem->isHit()) {
            return;
        }

        $this->migrateJobs();
        $this->migrateGroups();
        $this->migrateCounters();

        $cacheItem->set(true);
        $this->cache->save($cacheItem);
    }

    private function migrateGroups(): void
    {
        /** @var array<string> $toDelete */
        $toDelete = [];
        foreach ($this->extractUserIds('@community_groups_(?<user>.+?)_(?<instance>.+)@') as $userId) {
            $groups = $this->groupManager->getGroupsForUser($userId);
            foreach ($groups as $group) {
                $entity = (new CommunityGroup())
                    ->setUserId($userId)
                    ->setName($group->name)
                    ->setCommunityIds(array_map(static fn (Community $community) => $community->id, [...$group->communities]))
                ;
                $this->entityManager->persist($entity);
                $toDelete[] = $group->name;
            }
        }

        $this->entityManager->flush();
        foreach ($toDelete as $item) {
            $this->groupManager->deleteGroup($item);
        }
    }

    private function migrateCounters(): void
    {
        /** @var array<string, array<CounterConfiguration>> $toDelete */
        $toDelete = [];
        foreach ($this->extractUserIds('@counter.(?<user>.+?)___(?<instance>.+)@') as $cacheKey => $userId) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit() && $cacheItem->get() instanceof CounterConfiguration) {
                continue;
            }

            foreach ($this->countersRepository->getCountersForUser($userId) as $counter) {
                $entity = (new Counter())
                    ->setUserId($userId)
                    ->setName($counter->name)
                    ->setValue($counter->value)
                    ->setIncrementBy($counter->incrementBy)
                ;
                $this->entityManager->persist($entity);

                $toDelete[$userId] ??= [];
                $toDelete[$userId][] = $counter;
            }
        }

        $this->entityManager->flush();
        foreach ($toDelete as $userId => $counters) {
            foreach ($counters as $counter) {
                $this->countersRepository->delete($counter, $userId);
            }
        }
    }

    /**
     * @throws ReflectionException
     */
    private function migrateJobs(): void
    {
        /** @var array<Uuid> $jobsToDelete */
        $jobsToDelete = [];

        foreach ($this->listJobs() as $userId => $job) {
            if ($this->jobManager->isCancelled($job->last(MetadataStamp::class)->metadata['jobId'])) {
                continue;
            }
            $this->migrateJob($userId, $job, $jobsToDelete);
        }

        $this->entityManager->flush();
        foreach ($jobsToDelete as $job) {
            $this->jobManager->cancelJob($job);
        }
    }

    public function migrateJob(string $userId, object $job, array &$jobsToDelete = []): ?object
    {
        if ($job instanceof Envelope) {
            $object = $job->getMessage();
            $metadata = $job->last(MetadataStamp::class);
            assert($metadata !== null);
            $expiresAt = $metadata->metadata['expiresAt'];
            assert($expiresAt instanceof DateTimeInterface);
        } else {
            $object = $job;
            $metadata = null;
            $expiresAt = null;
        }

        $entity = null;

        if ($object instanceof CreatePostJob) {
            $image = null;
            if ($object->imageId) {
                try {
                    $oldFile = $this->fileUploader->get($object->imageId);
                    $image = $this->fileUploader->upload($oldFile);
                    $this->entityManager->persist($image);
                } catch (Exception) {
                    // ignore
                }
            }

            $entity = (new CreatePostStoredJob())
                ->setJwt($object->jwt)
                ->setInstance($object->instance)
                ->setCommunityId($object->community->id)
                ->setTitle($object->title)
                ->setUrl($object->url)
                ->setText($object->text)
                ->setLanguage($object->language)
                ->setNsfw($object->nsfw ?? false)
                ->setPinToCommunity($object->pinToCommunity)
                ->setPinToInstance($object->pinToInstance)
                ->setImage($image)
                ->setScheduleExpression($object->scheduleExpression)
                ->setScheduleTimezone($object->scheduleTimezone)
                ->setUnpinAt($object->unpinAt)
                ->setFileProviderId($object->fileProvider)
                ->setTimezoneName($object->timezoneName ?? 'UTC')
                ->setCheckForUrlDuplicates($object->checkForUrlDuplicates)
                ->setComments($object->comments)
                ->setThumbnailUrl($object->thumbnailUrl)
                ->setScheduledAt(DateTimeImmutable::createFromInterface($expiresAt ?? new DateTimeImmutable()))
                ->setUserId($userId)
            ;
            $this->entityManager->persist($entity);
        } elseif ($object instanceof PinUnpinPostJobV2) {
            $entity = (new PostPinUnpinStoredJob())
                ->setPostId($object->postId)
                ->setJwt($object->jwt)
                ->setInstance($object->instance)
                ->setScheduledAt(DateTimeImmutable::createFromInterface($expiresAt))
                ->setPinType($object->pin)
                ->setUserId($userId)
            ;
            $this->entityManager->persist($entity);
        } elseif ($object instanceof ReportUnreadPostsJob) {
            $entity = (new UnreadPostReportStoredJob())
                ->setJwt($object->jwt)
                ->setInstance($object->instance)
                ->setCommunityId($object->community?->id)
                ->setPersonId($object->person?->id)
                ->setScheduleExpression($object->scheduleExpression)
                ->setScheduleTimezone($object->scheduleTimezone)
                ->setScheduledAt(DateTimeImmutable::createFromInterface($expiresAt))
                ->setUserId($userId)
            ;
            $this->entityManager->persist($entity);
        }

        if ($metadata) {
            $jobsToDelete[] = $metadata->metadata['jobId'];
        }

        return $entity;
    }

    /**
     * @throws ReflectionException
     *
     * @return iterable<string, Envelope>
     */
    private function listJobs(): iterable
    {
        foreach ($this->extractUserIds('@job_list_(?<user>.+?)___(?<instance>.+)@') as $userId) {
            foreach ($this->jobManager->listJobsForUser($userId) as $job) {
                yield $userId => $job;
            }
        }
    }

    /**
     * @throws ReflectionException
     *
     * @return iterable<string, mixed>
     */
    private function extractUserIds(#[Language('RegExp')] string $regex): iterable
    {
        $cache = $this->cache;
        while ($cache instanceof TraceableAdapter) {
            $cache = $cache->getPool();
        }

        if ($cache instanceof FilesystemAdapter) {
            $directory = new ReflectionProperty($cache, 'directory');
            $scanHashDir = new ReflectionMethod($cache, 'scanHashDir');
            $getFileKey = new ReflectionMethod($cache, 'getFileKey');

            $files = $scanHashDir->invoke($cache, $directory->getValue($cache));
            foreach ($files as $file) {
                $key = $getFileKey->invoke($cache, $file);
                if (!preg_match($regex, $key, $matches)) {
                    continue;
                }
                $userId = $matches['user'] . '@' . $matches['instance'];

                yield $key => $userId;
            }
        } else {
            throw new LogicException('Unsupported cache type, cannot automatically migrate, please contact the developer with this message and include the following: ' . $cache::class);
        }
    }
}
