<?php

namespace App\Dto;

use ArrayIterator;
use IteratorAggregate;
use Rikudou\LemmyApi\Response\Model\Community;
use Traversable;

/**
 * @implements IteratorAggregate<int, CommunityGroup>
 */
final readonly class CommunityGroup implements IteratorAggregate
{
    /**
     * @param iterable<Community> $communities
     */
    public function __construct(
        public string $name,
        public iterable $communities,
    ) {
    }

    /**
     * @return Traversable<int, Community>
     */
    public function getIterator(): Traversable
    {
        return is_array($this->communities) ? new ArrayIterator($this->communities) : $this->communities;
    }
}
