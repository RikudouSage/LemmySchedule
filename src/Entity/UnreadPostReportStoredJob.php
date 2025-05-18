<?php

namespace App\Entity;

use App\Helper\CommonJobEntityFieldsTrait;
use App\Helper\SchedulableJobTrait;
use App\Repository\UnreadPostReportStoredJobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UnreadPostReportStoredJobRepository::class)]
class UnreadPostReportStoredJob
{
    use CommonJobEntityFieldsTrait;
    use SchedulableJobTrait;

    #[ORM\Column(nullable: true)]
    private ?int $communityId = null;

    #[ORM\Column(nullable: true)]
    private ?int $personId = null;

    public function getCommunityId(): ?int
    {
        return $this->communityId;
    }

    public function setCommunityId(?int $communityId): static
    {
        $this->communityId = $communityId;

        return $this;
    }

    public function getPersonId(): ?int
    {
        return $this->personId;
    }

    public function setPersonId(?int $personId): static
    {
        $this->personId = $personId;

        return $this;
    }
}
