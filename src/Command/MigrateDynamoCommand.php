<?php

namespace App\Command;

use App\FileUploader\FileUploader;
use App\Lemmy\LemmyApiFactory;
use App\Service\CommunityGroupManager;
use App\Service\CountersRepository as LegacyCounterRepository;
use App\Service\CurrentUserService;
use App\Service\DatabaseMigrator;
use App\Service\JobManager;
use App\Service\JobScheduler;
use AsyncAws\DynamoDb\DynamoDbClient;
use Doctrine\ORM\EntityManagerInterface;
use Rikudou\DynamoDbCache\DynamoDbCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand('app:migrate-dynamo', description: 'Migrates from a dynamo DB cache implementation to the new DB format.')]
final class MigrateDynamoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FileUploader           $fileUploader,
        private readonly CurrentUserService     $currentUserService,
        private readonly MessageBusInterface    $messageBus,
        private readonly LemmyApiFactory        $lemmyApiFactory,
        private readonly JobScheduler $jobScheduler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'table',
                mode: InputOption::VALUE_REQUIRED,
                description: 'The name of the original cache table',
            )
            ->addOption(
                name: 'primary-field',
                mode: InputOption::VALUE_REQUIRED,
                description: 'The name of the primary key field',
                default: 'id',
            )
            ->addOption(
                name: 'ttl-field',
                mode: InputOption::VALUE_REQUIRED,
                description: 'The name of the ttl field',
                default: 'ttl',
            )
            ->addOption(
                name: 'value-field',
                mode: InputOption::VALUE_REQUIRED,
                description: 'The name of the value field',
                default: 'value',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $client = new DynamoDbClient();
        $dynamoCache = new DynamoDbCache(
            tableName: $input->getOption('table') ?: throw new MissingInputException('Table name cannot be empty'),
            client: $client,
            primaryField: $input->getOption('primary-field'),
            ttlField: $input->getOption('ttl-field'),
            valueField: $input->getOption('value-field'),
        );
        $migrator = new DatabaseMigrator(
            jobManager: new JobManager(
                cache: $dynamoCache,
                currentUserService: $this->currentUserService,
                messageBus: $this->messageBus,
            ),
            cache: $dynamoCache,
            entityManager: $this->entityManager,
            fileUploader: $this->fileUploader,
            groupManager: new CommunityGroupManager(
                cache: $dynamoCache,
                currentUserService: $this->currentUserService,
                apiFactory: $this->lemmyApiFactory,
            ),
            countersRepository: new LegacyCounterRepository(
                currentUserService: $this->currentUserService,
                cache: $dynamoCache,
            ),
            jobScheduler: $this->jobScheduler,
        );

        $migrator->migrate();

        return Command::SUCCESS;
    }
}
