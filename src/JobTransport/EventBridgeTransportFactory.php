<?php

namespace App\JobTransport;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\Scheduler\SchedulerClient;
use LogicException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final readonly class EventBridgeTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private SchedulerClient $schedulerClient,
        private string $consoleFunctionArn,
        private string $roleArn,
    ) {
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new EventBridgeTransport(
            prefix: $this->getPrefix($dsn),
            consoleFunctionArn: $this->consoleFunctionArn,
            roleArn: $this->roleArn,
            serializer: $serializer,
            schedulerClient: $this->schedulerClient,
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'eb://');
    }

    private function getPrefix(string $dsn): string
    {
        return parse_url($dsn, PHP_URL_HOST) ?: throw new LogicException("Invalid DSN: {$dsn}");
    }
}
