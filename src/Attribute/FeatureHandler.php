<?php

namespace App\Attribute;

use App\Enum\Feature;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class FeatureHandler
{
    public function __construct(
        public Feature $feature,
    ) {
    }
}
