<?php

namespace App\InstanceList;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final readonly class InstanceListProviderCollection
{
    /**
     * @param iterable<InstanceListProvider> $providers
     */
    public function __construct(
        #[TaggedIterator('app.instance_list_provider')]
        private iterable $providers,
    ) {
    }

    /**
     * @return array<string>
     */
    public function getInstances(): array
    {
        foreach ($this->providers as $provider) {
            if ($provider->isReady()) {
                return $provider->getInstances();
            }
        }

        throw new RuntimeException('No provider is ready.');
    }
}
