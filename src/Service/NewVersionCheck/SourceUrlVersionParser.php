<?php

namespace App\Service\NewVersionCheck;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'app.source_url_version_parser')]
interface SourceUrlVersionParser
{
    public function supports(string $url): bool;

    public function getLatestVersion(string $url): string;
}
