<?php

namespace App\Dto;

use Countable;

final readonly class TitleExpressionParserResult implements Countable
{
    private int $count;

    /**
     * @param array<string> $validExpressions
     * @param array<string> $invalidExpressions
     */
    public function __construct(
        public array $validExpressions = [],
        public array $invalidExpressions = [],
    ) {
        $this->count = count($this->validExpressions);
    }

    public function count(): int
    {
        return $this->count;
    }
}
