<?php

namespace App\LivePropHydrator;

use DateTimeZone;
use Symfony\UX\LiveComponent\Hydration\HydrationExtensionInterface;

final class DateTimeZoneHydrator implements HydrationExtensionInterface
{
    public function supports(string $className): bool
    {
        return is_a($className, DateTimeZone::class, true);
    }

    public function hydrate(mixed $value, string $className): ?object
    {
        assert(is_string($value));
        assert(is_a($className, DateTimeZone::class, true));

        return new $className($value);
    }

    public function dehydrate(object $object): string
    {
        assert($object instanceof DateTimeZone);

        return $object->getName();
    }
}
