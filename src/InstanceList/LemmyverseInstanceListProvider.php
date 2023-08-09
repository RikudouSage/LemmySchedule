<?php

namespace App\InstanceList;

use Psr\Cache\CacheItemPoolInterface;

final readonly class LemmyverseInstanceListProvider implements InstanceListProvider
{
    public const CACHE_ITEM_NAME = 'provider_list.lemmyverse';

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function isReady(): bool
    {
        return $this->cache->getItem(self::CACHE_ITEM_NAME)->isHit();
    }

    public function getInstances(): array
    {
        $result = $this->cache->getItem(self::CACHE_ITEM_NAME)->get();
        assert(is_array($result));
        $result[] = 'lemmy.world';

        return array_unique($result);
    }
}
