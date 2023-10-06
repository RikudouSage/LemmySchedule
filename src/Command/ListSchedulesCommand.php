<?php

namespace App\Command;

use App\Job\CreatePostJob;
use App\JobStamp\RegistrableStamp;
use App\JobTransport\EventBridgeTransportFactory;
use App\Lemmy\LemmyApiFactory;
use AsyncAws\Scheduler\SchedulerClient;
use DateTimeImmutable;
use DateTimeZone;
use Rikudou\LemmyApi\Exception\HttpApiException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

#[AsCommand('app:schedules')]
final class ListSchedulesCommand extends Command
{
    public function __construct(
        private readonly EventBridgeTransportFactory $transportFactory,
        #[Autowire('%env(MESSENGER_TRANSPORT_DSN)%')]
        private readonly string $messengerTransportDsn,
        private readonly SchedulerClient $scheduler,
        private readonly SerializerInterface $serializer,
        private readonly LemmyApiFactory $apiFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->transportFactory->supports($this->messengerTransportDsn, [])) {
            $io->error('This only works for AWS EventBridge.');

            return Command::FAILURE;
        }

        $schedules = $this->scheduler->listSchedules([
            'NamePrefix' => $this->transportFactory->getPrefix($this->messengerTransportDsn),
        ]);

        $headers = [
            'Created',
            'Post date',
            'ID',
            'Community',
            'User',
            'Title',
            'URL',
            'Text',
            'Language',
            'NSFW',
            'Pin to community',
            'Pin to instance',
            'Has image',
            'Is recurring',
            'File provider',
        ];

        $jwtUserMap = [];

        $rows = [];
        foreach ($schedules->getSchedules() as $schedule) {
            $row = [
                $schedule->getCreationDate()?->setTimezone(new DateTimeZone('Europe/Prague'))->format('c'),
            ];

            $schedule = $this->scheduler->getSchedule(['Name' => $schedule->getName()]);

            $scheduleExpression = $schedule->getScheduleExpression();
            if (!str_starts_with($scheduleExpression, 'at(')) {
                $io->warning("Unsupported expression: {$scheduleExpression}");
                continue;
            }
            $scheduleExpression = substr($scheduleExpression, strlen('at('), -1);
            $scheduleDateTime = new DateTimeImmutable($scheduleExpression, new DateTimeZone($schedule->getScheduleExpressionTimezone()));

            $row[] = $scheduleDateTime->setTimezone(new DateTimeZone('Europe/Prague'))->format('c');

            $rawInput = $schedule->getTarget()?->getInput();
            assert($rawInput !== null);
            $rawInput = substr($rawInput, strlen('"app:run-sync '), -1);
            $base64Decoded = base64_decode($rawInput);
            $deserialized = unserialize($base64Decoded);
            $deserialized = $this->serializer->decode($deserialized);
            assert($deserialized instanceof Envelope);
            $job = $deserialized->getMessage();
            if (!$job instanceof CreatePostJob) {
                continue;
            }
            $api = $this->apiFactory->get($job->instance, jwt: $job->jwt);

            try {
                $jwtUserMap[$job->jwt] ??= $api->site()->getSite()->myUser?->localUserView->person->actorId;
            } catch (HttpApiException) {
                $jwtUserMap[$job->jwt] = '<error>Unknown</error>';
            }

            $row[] = (string) $deserialized->last(RegistrableStamp::class)?->jobId;
            $row[] = $job->community->actorId;
            $row[] = $jwtUserMap[$job->jwt];
            $row[] = $job->title;
            $row[] = $job->url ?? '<error>N/A</error>';
            $row[] = substr($job->text ?? '<error>N/A</error>', 0, 300);
            $row[] = $job->language->name;
            $row[] = $job->nsfw ? 'Yes' : 'No';
            $row[] = $job->pinToCommunity ? 'Yes' : 'No';
            $row[] = $job->pinToInstance ? 'Yes' : 'No';
            $row[] = $job->imageId ? 'Yes' : 'No';
            $row[] = $job->scheduleExpression ? 'Yes' : 'No';
            $row[] = $job->fileProvider ?? '<error>N/A</error>';

            $rows[] = $row;
        }
        usort($rows, static function (array $a, array $b) {
            $aDate = new DateTimeImmutable($a[1]);
            $bDate = new DateTimeImmutable($b[1]);

            return $aDate <=> $bDate;
        });

        $table = $io->createTable()
            ->setHeaders($headers)
            ->setRows($rows)
            ->setVertical();
        $table->render();
        $io->comment('Jobs: ' . count($rows));

        return Command::SUCCESS;
    }
}
