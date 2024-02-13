<?php

namespace App\Service\NewVersionCheck;

use App\Exception\VersionExtractionFailedException;
use DateInterval;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final readonly class NewVersionChecker
{
    /**
     * @param iterable<SourceUrlVersionParser> $parsers
     */
    public function __construct(
        #[Autowire('%app.current_version%')]
        private string $currentVersion,
        #[Autowire('%app.source_url%')]
        private string $sourceUrl,
        #[TaggedIterator('app.source_url_version_parser')]
        private iterable $parsers,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function hasNewVersion(): bool
    {
        if ($this->currentVersion === 'dev') {
            return false;
        }

        $latest = $this->getLatestVersion();
        if ($latest === null) {
            throw new VersionExtractionFailedException('Failed extracting the latest version from the source URL');
        }

        return version_compare($this->currentVersion, $latest) === -1;
    }

    public function getLatestVersion(): ?string
    {
        $cacheItem = $this->cache->getItem('app.latest_version');
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        foreach ($this->parsers as $parser) {
            if ($parser->supports($this->sourceUrl)) {
                $cacheItem->set($parser->getLatestVersion($this->sourceUrl));
                break;
            }
        }

        if (!$cacheItem->get()) {
            $cacheItem->set(null);
        }
        $cacheItem->expiresAfter(new DateInterval('PT12H'));
        $this->cache->save($cacheItem);

        return $cacheItem->get();
    }
}
