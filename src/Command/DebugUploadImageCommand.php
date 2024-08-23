<?php

namespace App\Command;

use App\Authentication\User;
use App\FileProvider\FileProvider;
use App\FileUploader\FileUploader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\HttpFoundation\File\File;

#[AsCommand('app:debug:upload-image')]
final class DebugUploadImageCommand extends Command
{
    /**
     * @param iterable<FileProvider> $fileProviders
     */
    public function __construct(
        #[TaggedIterator('app.file_provider')]
        private readonly iterable $fileProviders,
        private readonly FileUploader $fileUploader,
        private readonly string $debugJwt,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file-path', InputArgument::REQUIRED)
            ->addOption('provider', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $provider = null;
        $defaultProvider = null;
        foreach ($this->fileProviders as $fileProvider) {
            $defaultProvider ??= $fileProvider->isDefault() ? $fileProvider : null;
            $provider ??= $fileProvider->getId() === $input->getOption('provider') ? $fileProvider : null;
        }
        $provider ??= $defaultProvider;
        assert($provider !== null);

        $file = $input->getArgument('file-path');
        if (!file_exists($file)) {
            throw new IOException("The file '{$file}' does not exist");
        }

        $uuid = $this->fileUploader->upload(new File($file));
        $link = $provider->getLink($uuid, new User('rikudou', 'lemmings.world', $this->debugJwt));

        $output->writeln($link);

        return Command::SUCCESS;
    }
}
