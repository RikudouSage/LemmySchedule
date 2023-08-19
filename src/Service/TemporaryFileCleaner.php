<?php

namespace App\Service;

final class TemporaryFileCleaner
{
    /**
     * @var array<string>
     */
    private array $paths = [];

    public function __construct()
    {
        register_shutdown_function($this->cleanUpFiles(...));
    }

    public function cleanFileOnShutdown(string $path): void
    {
        $this->paths[] = $path;
    }

    private function cleanUpFiles(): void
    {
        foreach ($this->paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            unlink($path);
        }
    }
}
