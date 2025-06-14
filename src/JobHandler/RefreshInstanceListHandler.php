<?php

namespace App\JobHandler;

use App\InstanceList\LemmyverseInstanceListProvider;
use App\Job\RefreshInstanceListJob;
use DateInterval;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class RefreshInstanceListHandler
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private HttpClientInterface $httpClient,
        private MessageBusInterface $messageBus,
        private string $defaultInstance,
    ) {
    }

    public function __invoke(RefreshInstanceListJob $job): void
    {
        $cacheItem = $this->cache->getItem(LemmyverseInstanceListProvider::CACHE_ITEM_NAME);
        $cacheItem->expiresAfter(new DateInterval('P1D'));

        $json = json_decode(
            $this->httpClient->request(Request::METHOD_GET, 'https://data.lemmyverse.net/data/instance.min.json')->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        assert(is_array($json));
        usort($json, function (array $a, array $b) {
            if ($a['base'] === $this->defaultInstance) {
                return -1;
            }
            if ($b['base'] === $this->defaultInstance) {
                return 1;
            }

            return $b['score'] <=> $a['score'];
        });
        $instanceNames = array_map(static fn (array $instance) => $instance['base'], $json);

        $cacheItem->set($instanceNames);
        $this->cache->save($cacheItem);

        $this->messageBus->dispatch($job, [
            new DelayStamp(23 * 60 * 60 * 1_000),
        ]);
    }
}
