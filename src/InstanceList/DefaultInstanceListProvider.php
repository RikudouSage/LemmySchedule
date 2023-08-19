<?php

namespace App\InstanceList;

use App\Job\RefreshInstanceListJob;
use App\Service\JobManager;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: -1_000)]
final readonly class DefaultInstanceListProvider implements InstanceListProvider
{
    public function __construct(
        private string $defaultInstance,
        private JobManager $jobManager,
    ) {
    }

    public function isReady(): bool
    {
        return true;
    }

    public function getInstances(): array
    {
        $this->jobManager->createJob(new RefreshInstanceListJob(), null);

        return array_unique([
            $this->defaultInstance,
            'lemmings.world',
            'lemmy.world',
            'lemmy.ml',
            'sh.itjust.works',
            'lemm.ee',
            'beehaw.org',
            'feddit.de',
            'lemmy.dbzer0.com',
            'lemmy.one',
        ]);
    }
}
