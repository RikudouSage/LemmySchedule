<?php

namespace App\Dto;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
final readonly class CounterConfiguration
{
    public function __construct(
        public string $name,
        public int $value,
        public int $incrementBy,
    ) {
    }
}
