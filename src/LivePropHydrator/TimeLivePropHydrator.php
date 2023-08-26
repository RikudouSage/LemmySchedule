<?php

namespace App\LivePropHydrator;

use App\Dto\Time;
use Symfony\UX\LiveComponent\Hydration\HydrationExtensionInterface;

final class TimeLivePropHydrator implements HydrationExtensionInterface
{
    public function supports(string $className): bool
    {
        return $className === Time::class;
    }

    public function hydrate(mixed $value, string $className): ?object
    {
        assert(is_string($value));
        assert($className === Time::class);

        return Time::fromString($value);
    }

    public function dehydrate(object $object): string
    {
        assert($object instanceof Time);

        return (string) $object;
    }
}
