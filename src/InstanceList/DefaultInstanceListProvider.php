<?php

namespace App\InstanceList;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: -1_000)]
final readonly class DefaultInstanceListProvider implements InstanceListProvider
{
    public function isReady(): bool
    {
        return true;
    }

    public function getInstances(): array
    {
        return [
            'lemmings.world',
            'lemmy.world',
            'lemmy.ml',
            'sh.itjust.works',
            'lemm.ee',
            'beehaw.org',
            'feddit.de',
            'lemmy.dbzer0.com',
            'lemmy.one',
        ];
    }
}
