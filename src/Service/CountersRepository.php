<?php

namespace App\Service;

use App\Dto\CounterConfiguration;
use JetBrains\PhpStorm\Deprecated;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Deprecated]
final readonly class CountersRepository
{
    public function __construct(
        private CurrentUserService $currentUserService,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return array<CounterConfiguration>
     */
    public function getCounters(): array
    {
        return $this->getCountersForUser(
            $this->currentUserService->getCurrentUser()->getUserIdentifier(),
        );
    }

    /**
     * @return array<CounterConfiguration>
     */
    public function getCountersForUser(string $userId): array
    {
        $cacheItem = $this->getListItem($userId);
        if (!$cacheItem->isHit()) {
            return [];
        }

        return array_filter(array_map(
            fn (string $name) => $this->findByName($name),
            array_unique($cacheItem->get()),
        ));
    }

    public function findByName(string $name, ?string $userId = null): ?CounterConfiguration
    {
        $userId ??= $this->currentUserService->getCurrentUser()->getUserIdentifier();

        $key = $this->getItemKey($name, $userId);
        $item = $this->cache->getItem($key);
        if (!$item->isHit()) {
            return null;
        }

        return $item->get();
    }

    public function store(CounterConfiguration $counterConfiguration, ?string $userId = null): void
    {
        $userId ??= $this->currentUserService->getCurrentUser()->getUserIdentifier();

        $item = $this->cache->getItem($this->getItemKey($counterConfiguration, $userId));
        $item->set($counterConfiguration);

        $listItem = $this->getListItem($userId);
        $list = $listItem->get() ?? [];
        $list = $this->cleanupList($list);
        $list[] = $counterConfiguration->name;
        $list = array_unique($list);

        $listItem->set($list);

        $this->cache->save($item);
        $this->cache->save($listItem);
    }

    public function delete(CounterConfiguration|string $counterConfiguration, ?string $userId = null): void
    {
        $userId ??= $this->currentUserService->getCurrentUser()->getUserIdentifier();

        $this->cache->deleteItem($this->getItemKey($counterConfiguration, $userId));

        $listItem = $this->getListItem($userId);
        $list = $listItem->get() ?? [];
        $list = $this->cleanupList($list);
        $listItem->set($list);

        $this->cache->save($listItem);
    }

    private function getItemKey(CounterConfiguration|string $configuration, string $userId): string
    {
        if (!is_string($configuration)) {
            $configuration = $configuration->name;
        }

        return $this->normalizeKey("counter.{$userId}.{$configuration}");
    }

    private function normalizeKey(string $key): string
    {
        $reservedCharacters = str_split(ItemInterface::RESERVED_CHARACTERS);

        return str_replace(
            $reservedCharacters,
            '___',
            $key,
        );
    }

    private function getListItem(string $userId): CacheItemInterface
    {
        $listKey = $this->normalizeKey("counter.{$userId}");

        return $this->cache->getItem($listKey);
    }

    /**
     * @param array<string> $list
     *
     * @return array<string>
     */
    private function cleanupList(array $list): array
    {
        foreach ($list as $key => $item) {
            if ($this->findByName($item) === null) {
                unset($list[$key]);
            }
        }

        return array_values($list);
    }
}
