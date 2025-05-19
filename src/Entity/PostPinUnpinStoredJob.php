<?php

namespace App\Entity;

use App\Helper\CommonJobEntityFieldsTrait;
use App\Repository\PostPinUnpinStoredJobRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\PinType;

#[ORM\Entity(repositoryClass: PostPinUnpinStoredJobRepository::class)]
class PostPinUnpinStoredJob
{
    use CommonJobEntityFieldsTrait;

    #[ORM\Column]
    private ?int $postId = null;

    #[ORM\Column(enumType: PinType::class)]
    private ?PinType $pinType = null;

    public function getPostId(): ?int
    {
        return $this->postId;
    }

    public function setPostId(int $postId): static
    {
        $this->postId = $postId;

        return $this;
    }

    public function getPinType(): ?PinType
    {
        return $this->pinType;
    }

    public function setPinType(PinType $pinType): static
    {
        $this->pinType = $pinType;

        return $this;
    }
}
