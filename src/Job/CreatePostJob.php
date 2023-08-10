<?php

namespace App\Job;

use Rikudou\LemmyApi\Enum\Language;
use Rikudou\LemmyApi\Response\Model\Community;

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
    ) {
    }

    /**
     * For adding properties to already serialized objects
     */
    public function __unserialize(array $data): void
    {
        $data['pinToCommunity'] ??= false;

        foreach ($data as $property => $value) {
            $this->{$property} = $value;
        }
    }
}
