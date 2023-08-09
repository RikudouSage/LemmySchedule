<?php

namespace App\JobStamp;

use ArrayIterator;
use IteratorAggregate;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Traversable;

final readonly class MetadataStamp implements IteratorAggregate, StampInterface
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $metadata,
    ) {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->metadata);
    }
}
