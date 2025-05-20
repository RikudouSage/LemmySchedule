<?php

namespace App\Service;

use App\Dto\CommunityGroup;
use App\Exception\UserNotLoggedInException;
use App\Lemmy\LemmyApiFactory;
use JetBrains\PhpStorm\Deprecated;
use Psr\Cache\CacheItemPoolInterface;
use Rikudou\LemmyApi\Response\Model\Community;

#[Deprecated]
final readonly class CommunityGroupManager
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private CurrentUserService $currentUserService,
        private LemmyApiFactory $apiFactory,
    ) {
    }

    /**
     * @return iterable<CommunityGroup>
     */
    public function getGroups(): iterable
    {
        $currentUser = $this->currentUserService->getCurrentUser();
        if (!$currentUser) {
            throw new UserNotLoggedInException('There is no logged in user');
        }

        return $this->getGroupsForUser($currentUser->getUserIdentifier());
    }

    /**
     * @return iterable<CommunityGroup>
     */
    public function getGroupsForUser(string $userId): iterable
    {
        $normalized = str_replace('@', '_', $userId);
        $cacheItem = $this->cache->getItem("community_groups_{$normalized}");
        $groups = $cacheItem->isHit() ? $cacheItem->get() : [];
        assert(is_array($groups));

        $api = $this->apiFactory->get(instance: parse_url("https://{$userId}", PHP_URL_HOST));

        foreach ($groups as $group) {
            assert(isset($group['name']));
            assert(isset($group['community_ids']));

            yield new CommunityGroup(
                name: $group['name'],
                communities: array_map(
                    static fn (int $id) => $api->community()->get($id)->community,
                    $group['community_ids'],
                ),
            );
        }
    }

    public function getGroup(string $title): ?CommunityGroup
    {
        $groups = $this->getGroups();
        foreach ($groups as $group) {
            if ($group->name === $title) {
                return $group;
            }
        }

        return null;
    }

    /**
     * @param iterable<Community> $communities
     */
    public function addGroup(string $title, iterable $communities): void
    {
        $currentUser = $this->currentUserService->getCurrentUser();
        if (!$currentUser) {
            throw new UserNotLoggedInException('There is no logged in user');
        }
        $cacheItem = $this->cache->getItem("community_groups_{$currentUser->getUsername()}_{$currentUser->getInstance()}");
        $groups = $cacheItem->isHit() ? $cacheItem->get() : [];

        $groups[] = [
            'name' => $title,
            'community_ids' => array_map(static fn (Community $community) => $community->id, [...$communities]),
        ];
        $cacheItem->set($groups);
        $this->cache->save($cacheItem);
    }

    public function deleteGroup(string $title): void
    {
        $currentUser = $this->currentUserService->getCurrentUser();
        if (!$currentUser) {
            throw new UserNotLoggedInException('There is no logged in user');
        }
        $cacheItem = $this->cache->getItem("community_groups_{$currentUser->getUsername()}_{$currentUser->getInstance()}");
        $groups = $cacheItem->isHit() ? $cacheItem->get() : [];
        $groups = array_filter($groups, static fn (array $group) => $group['name'] !== $title);
        $cacheItem->set($groups);
        $this->cache->save($cacheItem);
    }
}
