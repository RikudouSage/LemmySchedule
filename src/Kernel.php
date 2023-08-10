<?php

namespace App;

use Bref\SymfonyBridge\BrefKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

class Kernel extends BrefKernel
{
    use MicroKernelTrait;

    public function getBuildDir(): string
    {
        if ($this->environment !== 'prod' && $this->isLambda()) {
            return '/tmp/cache/' . $this->environment;
        }

        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }
}
