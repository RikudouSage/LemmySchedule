<?php

namespace App\Service;

use App\Entity\CreatePostStoredJob;
use App\FileUploader\FileUploader;
use App\Job\CreatePostJob;
use App\Job\PinUnpinPostJobV2;
use App\JobStamp\MetadataStamp;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Uid\Uuid;

final readonly class DatabaseMigrator
{
    public function __construct(
        private JobManager             $jobManager,
        private CacheItemPoolInterface $cache,
        private EntityManagerInterface $entityManager, private FileUploader $fileUploader,
    ) {
    }

    public function migrate(): void
    {
        $cacheItem = $this->cache->getItem('migration_v2_succeeded');
        if ($cacheItem->isHit()) {
            return;
        }

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

        $cacheItem->set(true);
        $this->cache->save($cacheItem);
    }

    private function migrateJob(string $userId, Envelope $job, array &$jobsToDelete): void
    {
        $object = $job->getMessage();
        $metadata = $job->last(MetadataStamp::class);
        assert($metadata !== null);

        if ($object instanceof CreatePostJob) {
            $expiresAt = $metadata->metadata['expiresAt'];
            assert($expiresAt instanceof DateTimeInterface);
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
                ->setScheduledAt(DateTimeImmutable::createFromInterface($expiresAt))
                ->setUserId($userId)
            ;
            $this->entityManager->persist($entity);
        } else if ($object instanceof PinUnpinPostJobV2) {

        }

        $jobsToDelete[] = $metadata->metadata['jobId'];
    }

    /**
     * @return iterable<string, Envelope>
     */
    private function listJobs(): iterable
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
                if (!fnmatch('job_list_*', $key)) {
                    continue;
                }

                $regex = "@job_list_(?<user>.+?)___(?<instance>.+)@";
                if (!preg_match($regex, $key, $matches)) {
                    continue;
                }
                $userId = $matches['user'] . '@' . $matches['instance'];

                foreach ($this->jobManager->listJobsForUser($userId) as $job) {
                    yield $userId => $job;
                }
            }
        } else {
            throw new LogicException('Unsupported cache type, cannot automatically migrate, please contact the developer with this message and include the following: ' . $cache::class);
        }
    }
}
