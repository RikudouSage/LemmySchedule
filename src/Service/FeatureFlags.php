<?php

namespace App\Service;

use Unleash\Client\Bootstrap\BootstrapProvider;

final readonly class FeatureFlags implements BootstrapProvider
{
    /**
     * @param array<string, bool> $flags
     */
    public function __construct(
        private array $flags,
    ) {
    }

    public function getBootstrap(): array
    {
        $result = [
            'features' => [],
        ];
        foreach ($this->flags as $flag => $enabled) {
            $result['features'][] = [
                'name' => $flag,
                'enabled' => true,
                'strategies' => [
                    [
                        'name' => 'flexibleRollout',
                        'parameters' => [
                            'rollout' => $enabled ? 100 : 0,
                            'stickiness' => 'default',
                        ],
                    ],
                    [
                        'name' => 'userWithId',
                        'parameters' => [
                            'userIds' => 'rikudou@lemmings.world',
                        ],
                    ],
                ],
            ];
        }

        return $result;
    }
}
