<?php

namespace App\Entity;

use App\Helper\DefaultIdEntityTrait;
use App\Repository\CommunityGroupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Index(fields: ['userId'])]
#[ORM\Entity(repositoryClass: CommunityGroupRepository::class)]
class CommunityGroup
{
    use DefaultIdEntityTrait;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var array<int>
     */
    #[ORM\Column]
    private array $communityIds = [];

    #[ORM\Column(length: 255)]
    private ?string $userId = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return array<int>
     */
    public function getCommunityIds(): array
    {
        return $this->communityIds;
    }

    /**
     * @param array<int> $communityIds
     */
    public function setCommunityIds(array $communityIds): static
    {
        $this->communityIds = $communityIds;

        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): static
    {
        $this->userId = $userId;

        return $this;
    }
}
