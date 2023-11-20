<?php

namespace App\Component;

use App\Authentication\User;
use App\Service\CurrentUserService;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class CommunitySelectComponent
{
    use DefaultActionTrait;

    public ?array $communities = null;

    public array $selectedCommunities = [];

    public string $name = 'communities';

    public ?string $id = null;

    public function __construct(
        private readonly CurrentUserService $currentUserService,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function mount(): void
    {
        $this->communities = $this->getCommunities();
    }

    private function getCommunities()
    {
        $user = $this->currentUserService->getCurrentUser();
        assert($user instanceof User);
        $cacheItem = $this->cache->getItem("community_list_{$user->getInstance()}");

        return $cacheItem->isHit() ? $cacheItem->get() : [];
    }
}
