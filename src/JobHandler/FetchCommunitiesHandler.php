<?php

namespace App\JobHandler;

use App\Job\FetchCommunitiesJob;
use App\Lemmy\LemmyApiFactory;
use Psr\Cache\CacheItemPoolInterface;
use Rikudou\LemmyApi\Enum\ListingType;
use Rikudou\LemmyApi\Enum\SortType;
use Rikudou\LemmyApi\LemmyApi;
use Rikudou\LemmyApi\Response\View\CommentView;
use Rikudou\LemmyApi\Response\View\CommunityView;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FetchCommunitiesHandler
{
    public function __construct(
        private LemmyApiFactory $apiFactory,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function __invoke(FetchCommunitiesJob $job): void
    {
        $cacheItem = $this->cache->getItem("community_list_{$job->instance}");
        $api = $this->apiFactory->get(instance: $job->instance, jwt: $job->jwt);
        $communities = $this->getCommunities($api);
        $communities = array_map(
            fn (CommunityView $community) => '!' . $community->community->name . '@' . parse_url($community->community->actorId, PHP_URL_HOST),
            [...$communities],
        );

        $cacheItem->set($communities);
        $this->cache->save($cacheItem);
    }

    /**
     * @return iterable<CommentView>
     */
    public function getCommunities(LemmyApi $api): iterable
    {
        $totalLimit = 2_000;
        $page = 1;
        $i = 0;
        do {
            if ($i > $totalLimit) {
                break;
            }
            $communities = $api->community()->list(
                limit: 20,
                page: $page,
                sort: SortType::TopAll,
                listingType: ListingType::All,
                showNsfw: true,
            );

            yield from $communities;
            $i += count($communities);
            ++$page;
        } while (count($communities));
    }
}
