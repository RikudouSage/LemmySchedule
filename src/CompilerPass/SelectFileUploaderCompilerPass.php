<?php

namespace App\CompilerPass;

use App\FileUploader\FileUploader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SelectFileUploaderCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $service = $container->resolveEnvPlaceholders($container->getParameter('app.file_uploader.class'), true);
        $container->setAlias(FileUploader::class, $service);
    }
}
