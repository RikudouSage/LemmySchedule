<?php

namespace App\Job;

use JetBrains\PhpStorm\Deprecated;
use Rikudou\LemmyApi\Response\Model\Community;
use Rikudou\LemmyApi\Response\Model\Person;

#[Deprecated]
final readonly class ReportUnreadPostsJob
{
    public function __construct(
        public string $jwt,
        public string $instance,
        public ?Community $community = null,
        public ?Person $person = null,
        public ?string $scheduleExpression = null,
        public ?string $scheduleTimezone = null,
    ) {
    }

    public function __unserialize(array $data): void
    {
        $data['person'] ??= null;

        foreach ($data as $property => $value) {
            $this->{$property} = $value;
        }
    }
}
