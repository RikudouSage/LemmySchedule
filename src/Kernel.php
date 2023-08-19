<?php

namespace App;

use App\CompilerPass\SelectFileUploaderCompilerPass;
use Bref\SymfonyBridge\BrefKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Kernel extends BrefKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new SelectFileUploaderCompilerPass());
    }

    public function getBuildDir(): string
    {
        if ($this->environment !== 'prod' && $this->isLambda()) {
            return '/tmp/cache/' . $this->environment;
        }

        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }
}
