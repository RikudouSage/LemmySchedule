<?php

namespace App\InstanceList;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.instance_list_provider')]
interface InstanceListProvider
{
    public function isReady(): bool;

    /**
     * @return array<string>
     */
    public function getInstances(): array;
}
