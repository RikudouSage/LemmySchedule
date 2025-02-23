<?php

namespace App\Service;

use App\Attribute\FeatureHandler;
use App\Authentication\User;
use App\Enum\Feature;
use App\Lemmy\LemmyApiFactory;
use LogicException;
use ReflectionObject;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SupportedFeaturesManager
{
    public function __construct(
        private Security $security,
        private LemmyApiFactory $apiFactory,
    ) {
    }

    public function supports(Feature $feature): bool
    {
        /** @var callable|null $handler */
        $handler = $this->findHandler($feature);
        if ($handler === null) {
            throw new LogicException('Unhandled feature: ' . $feature->name);
        }

        $user = $this->security->getToken()?->getUser() ??
            throw new LogicException('No one is logged in');
        assert($user instanceof User);

        return $handler($user);
    }

    private function findHandler(Feature $feature): ?callable
    {
        $reflection = new ReflectionObject($this);
        $methods = $reflection->getMethods();

        foreach ($methods as $method) {
            $attribute = $method->getAttributes(FeatureHandler::class);
            if (!count($attribute)) {
                continue;
            }
            $attribute = $attribute[array_key_first($attribute)]->newInstance();
            assert($attribute instanceof FeatureHandler);

            if ($attribute->feature === $feature) {
                return $method->getClosure($this);
            }
        }

        return null;
    }

    #[FeatureHandler(feature: Feature::ThumbnailUrl)]
    private function supportsThumbnailUrls(User $user): bool
    {
        $api = $this->apiFactory->getForUser($user);
        $version = $api->site()->getSite()->version;

        return version_compare($version, '0.19.4', '>=');
    }
}
