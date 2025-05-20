<?php

namespace App\Entity;

use App\Helper\CommonJobEntityFieldsTrait;
use App\Helper\SchedulableJobTrait;
use App\Repository\CreatePostStoredJobRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Rikudou\LemmyApi\Enum\Language;

#[ORM\Index(fields: ['userId'])]
#[ORM\Entity(repositoryClass: CreatePostStoredJobRepository::class)]
class CreatePostStoredJob
{
    use CommonJobEntityFieldsTrait;
    use SchedulableJobTrait;

    #[ORM\Column]
    private ?int $communityId = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $text = null;

    #[ORM\Column(enumType: Language::class)]
    private Language $language = Language::Undetermined;

    #[ORM\Column]
    private bool $nsfw = false;

    #[ORM\Column]
    private bool $pinToCommunity = false;

    #[ORM\Column]
    private bool $pinToInstance = false;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $unpinAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileProviderId = null;

    #[ORM\Column]
    private bool $checkForUrlDuplicates = false;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $comments = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnailUrl = null;

    #[ORM\OneToOne(inversedBy: 'createPostJob', cascade: ['persist', 'remove'])]
    private ?StoredFile $image = null;

    public function getCommunityId(): ?int
    {
        return $this->communityId;
    }

    public function setCommunityId(int $communityId): static
    {
        $this->communityId = $communityId;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getLanguage(): ?Language
    {
        return $this->language;
    }

    public function setLanguage(Language $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function isNsfw(): ?bool
    {
        return $this->nsfw;
    }

    public function setNsfw(bool $nsfw): static
    {
        $this->nsfw = $nsfw;

        return $this;
    }

    public function shouldPinToCommunity(): ?bool
    {
        return $this->pinToCommunity;
    }

    public function setPinToCommunity(bool $pinToCommunity): static
    {
        $this->pinToCommunity = $pinToCommunity;

        return $this;
    }

    public function shouldPinToInstance(): ?bool
    {
        return $this->pinToInstance;
    }

    public function setPinToInstance(bool $pinToInstance): static
    {
        $this->pinToInstance = $pinToInstance;

        return $this;
    }

    public function getUnpinAt(): ?DateTimeImmutable
    {
        return $this->unpinAt;
    }

    public function setUnpinAt(?DateTimeImmutable $unpinAt): static
    {
        $this->unpinAt = $unpinAt;

        return $this;
    }

    public function getFileProviderId(): ?string
    {
        return $this->fileProviderId;
    }

    public function setFileProviderId(?string $fileProviderId): static
    {
        $this->fileProviderId = $fileProviderId;

        return $this;
    }

    public function shouldCheckForUrlDuplicates(): ?bool
    {
        return $this->checkForUrlDuplicates;
    }

    public function setCheckForUrlDuplicates(bool $checkForUrlDuplicates): static
    {
        $this->checkForUrlDuplicates = $checkForUrlDuplicates;

        return $this;
    }

    public function getComments(): array
    {
        return $this->comments;
    }

    public function setComments(array $comments): static
    {
        $this->comments = $comments;

        return $this;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }

    public function setThumbnailUrl(?string $thumbnailUrl): static
    {
        $this->thumbnailUrl = $thumbnailUrl;

        return $this;
    }

    public function getImage(): ?StoredFile
    {
        return $this->image;
    }

    public function setImage(?StoredFile $image): static
    {
        $this->image = $image;

        return $this;
    }
}
