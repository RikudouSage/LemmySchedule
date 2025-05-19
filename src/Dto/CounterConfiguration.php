<?php

namespace App\Dto;

final readonly class CounterConfiguration
{
    public function __construct(
        public string $name,
        public int $value,
        public int $incrementBy,
    ) {
    }
}
