<?php

namespace App\JobTransport;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\Scheduler\Enum\ActionAfterCompletion;
use AsyncAws\Scheduler\Enum\FlexibleTimeWindowMode;
use AsyncAws\Scheduler\Enum\ScheduleState;
use AsyncAws\Scheduler\Input\CreateScheduleInput;
use AsyncAws\Scheduler\SchedulerClient;
use AsyncAws\Scheduler\ValueObject\FlexibleTimeWindow;
use AsyncAws\Scheduler\ValueObject\RetryPolicy;
use AsyncAws\Scheduler\ValueObject\Target;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Uid\Uuid;

final readonly class EventBridgeTransport implements TransportInterface
{
    public function __construct(
        private string $prefix,
        private string $consoleFunctionArn,
        private string $roleArn,
        private SerializerInterface $serializer,
        private SchedulerClient $schedulerClient,
    ) {
    }

    public function get(): iterable
    {
        // TODO: Implement get() method.
    }

    public function ack(Envelope $envelope): void
    {
        // TODO: Implement ack() method.
    }

    public function reject(Envelope $envelope): void
    {
        // TODO: Implement reject() method.
    }

    public function send(Envelope $envelope): Envelope
    {
        $uuid = Uuid::v4();
        $envelope = $envelope->with(new TransportMessageIdStamp((string) $uuid));
        $encoded = $this->serializer->encode($envelope);
        $delay = $envelope->last(DelayStamp::class);
        $runAt = new DateTimeImmutable();
        if ($delay !== null) {
            $delay = $delay->getDelay() / 1_000;
            $runAt = $runAt->add(new DateInterval("PT{$delay}S"));
        }

        $this->schedulerClient->createSchedule(new CreateScheduleInput([
            'ActionAfterCompletion' => ActionAfterCompletion::DELETE,
            'FlexibleTimeWindow' => new FlexibleTimeWindow([
                'Mode' => FlexibleTimeWindowMode::OFF,
            ]),
            'Name' => "{$this->prefix}_Job_{$uuid}",
            'ScheduleExpression' => "at({$runAt->format('Y-m-d\TH:i:s')})",
            'ScheduleExpressionTimezone' => $runAt->getTimezone()->getName(),
            'State' => ScheduleState::ENABLED,
            'Target' => new Target([
                'Arn' => $this->consoleFunctionArn,
                'RoleArn' => $this->roleArn,
                'Input' => '"app:run-sync ' . base64_encode(json_encode($encoded, flags: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '"',
                'RetryPolicy' => new RetryPolicy([
                    'MaximumEventAgeInSeconds' => 86_400,
                    'MaximumRetryAttempts' => 5,
                ]),
            ]),
        ]));

        return $envelope;
    }
}
