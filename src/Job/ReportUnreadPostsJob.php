<?php

namespace App\Job;

use Rikudou\LemmyApi\Response\Model\Community;

final readonly class ReportUnreadPostsJob
{
    public function __construct(
        public string $jwt,
        public string $instance,
        public Community $community,
        public ?string $scheduleExpression = null,
        public ?string $scheduleTimezone = null,
    ) {}
}
