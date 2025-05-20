<?php

namespace App\Entity;

use App\Helper\DefaultIdEntityTrait;
use App\Repository\CounterRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Index(fields: ['userId'])]
#[ORM\Entity(repositoryClass: CounterRepository::class)]
class Counter
{
    use DefaultIdEntityTrait;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private int $value = 0;

    #[ORM\Column]
    private int $incrementBy = 1;

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

    public function getValue(): int
    {
        return $this->value;
    }

    public function setValue(int $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getIncrementBy(): int
    {
        return $this->incrementBy;
    }

    public function setIncrementBy(int $incrementBy): static
    {
        $this->incrementBy = $incrementBy;

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
