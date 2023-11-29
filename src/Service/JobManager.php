<?php

namespace App\Service;

use App\JobStamp\CancellableStamp;
use App\JobStamp\MetadataStamp;
use App\JobStamp\RegistrableStamp;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Uid\Uuid;

final readonly class JobManager
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private CurrentUserService $currentUserService,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function isCancelled(Uuid $jobId): bool
    {
        return $this->cache->getItem("cancelled_job_{$jobId}")->isHit();
    }

    public function cancelJob(Uuid $jobId, ?DateTimeInterface $expiresAt = null): void
    {
        $expiresAt ??= (new DateTimeImmutable())->add(new DateInterval('P7D'));
        $cacheItem = $this->cache->getItem("cancelled_job_{$jobId}");
        $cacheItem->set(true);
        $cacheItem->expiresAt($expiresAt);
        $this->cache->save($cacheItem);
    }

    public function registerJob(Uuid $jobId, object $message, ?DateTimeInterface $expiresAt = null): void
    {
        $expiresAt ??= (new DateTimeImmutable())->add(new DateInterval('P7D'));
        $cacheKey = "job_{$jobId}";
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            return;
        }
        $cacheItem->set(new Envelope(message: $message, stamps: [new MetadataStamp([
            'expiresAt' => $expiresAt,
            'jobId' => $jobId,
        ])]));
        $cacheItem->expiresAt($expiresAt);
        $this->cache->save($cacheItem);

        $cacheItemList = $this->cache->getItem("job_list_{$this->getUserIdentifier()}");
        $list = $cacheItemList->isHit() ? $cacheItemList->get() : [];
        assert(is_array($list));
        $list[] = $cacheKey;
        $cacheItemList->set($list);
        $this->cache->save($cacheItemList);
    }

    public function getJob(Uuid $jobId): ?Envelope
    {
        $cacheKey = "job_{$jobId}";
        $cacheItemList = $this->cache->getItem("job_list_{$this->getUserIdentifier()}");
        $list = $cacheItemList->isHit() ? $cacheItemList->get() : [];
        if (!in_array($cacheKey, $list, true)) {
            return null;
        }

        return $this->cache->getItem($cacheKey)->get();
    }

    /**
     * @return array<Envelope>
     */
    public function listJobs(): array
    {
        $cacheItemList = $this->cache->getItem("job_list_{$this->getUserIdentifier()}");
        $list = $cacheItemList->isHit() ? $cacheItemList->get() : [];

        $changed = false;
        $result = [];
        foreach ($list as $index => $cacheKey) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if (!$cacheItem->isHit()) {
                unset($list[$index]);
                $changed = true;
                continue;
            }

            $result[] = $cacheItem->get();
        }
        if ($changed) {
            $list = array_values($list);
            $cacheItemList->set($list);
            $this->cache->save($cacheItemList);
        }

        return $result;
    }

    public function createJob(object $message, ?DateTimeInterface $runAt): void
    {
        $jobId = Uuid::v4();
        $stamps = [
            new CancellableStamp($jobId),
            new RegistrableStamp($jobId),
        ];
        if ($runAt !== null) {
            $stamps[] = DelayStamp::delayUntil($runAt);
        }

        $this->messageBus->dispatch($message, $stamps);
    }

    /**
     * @return array<Envelope>
     */
    public function getActiveJobsByType(string $type): iterable
    {
        return array_filter($this->listJobs(), function (Envelope $envelope) use ($type) {
            $jobId = $envelope->last(MetadataStamp::class)?->metadata['jobId'];
            if (!$jobId) {
                return false;
            }
            if ($this->isCancelled($jobId)) {
                return false;
            }

            if (!is_a($envelope->getMessage(), $type, true)) {
                return false;
            }

            return true;
        });
    }

    private function getUserIdentifier(): string
    {
        return str_replace('@', '___', $this->currentUserService->getCurrentUser()?->getUserIdentifier() ?? '');
    }
}
