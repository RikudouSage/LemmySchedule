<?php

namespace App\Helper;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

trait CommonJobEntityFieldsTrait
{
    use DefaultIdEntityTrait;

    #[ORM\Column(length: 255)]
    private ?string $jwt = null;

    #[ORM\Column(length: 255)]
    private ?string $instance = null;

    #[ORM\Column(length: 255)]
    private ?string $userId = null;

    #[ORM\Column]
    private ?DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(length: 255)]
    private string $timezoneName = 'UTC';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJwt(): ?string
    {
        return $this->jwt;
    }

    public function setJwt(string $jwt): static
    {
        $this->jwt = $jwt;

        return $this;
    }

    public function getInstance(): ?string
    {
        return $this->instance;
    }

    public function setInstance(string $instance): static
    {
        $this->instance = $instance;

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

    public function getScheduledAt(): ?DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getTimezoneName(): string
    {
        return $this->timezoneName;
    }

    public function setTimezoneName(string $timezoneName): static
    {
        $this->timezoneName = $timezoneName;

        return $this;
    }
}
