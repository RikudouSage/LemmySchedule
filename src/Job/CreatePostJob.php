<?php

namespace App\Job;

use DateTimeInterface;
use JetBrains\PhpStorm\Deprecated;
use Rikudou\LemmyApi\Enum\Language;
use Rikudou\LemmyApi\Response\Model\Community;
use Symfony\Component\Uid\Uuid;

#[Deprecated]
final readonly class CreatePostJob
{
    public function __construct(
        public string $jwt,
        public string $instance,
        public Community $community,
        public string $title,
        public ?string $url = null,
        public ?string $text = null,
        public Language $language = Language::Undetermined,
        public ?bool $nsfw = null,
        public bool $pinToCommunity = false,
        public bool $pinToInstance = false,
        public ?Uuid $imageId = null,
        public ?string $scheduleExpression = null,
        public ?string $scheduleTimezone = null,
        public ?DateTimeInterface $unpinAt = null,
        public ?string $fileProvider = null,
        public ?string $timezoneName = null,
        public bool $checkForUrlDuplicates = false,
        public array $comments = [],
        public ?string $thumbnailUrl = null,
    ) {
    }

    /**
     * For adding properties to already serialized objects
     */
    public function __unserialize(array $data): void
    {
        $data['pinToCommunity'] ??= false;
        $data['pinToInstance'] ??= false;
        $data['imageId'] ??= null;
        $data['scheduleExpression'] ??= null;
        $data['scheduleTimezone'] ??= null;
        $data['unpinAt'] ??= null;
        $data['fileProvider'] ??= null;
        $data['timezoneName'] ??= null;
        $data['checkForUrlDuplicates'] ??= false;
        $data['comments'] ??= [];
        $data['thumbnailUrl'] ??= null;

        foreach ($data as $property => $value) {
            $this->{$property} = $value;
        }
    }
}
