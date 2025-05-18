<?php

namespace App\Helper;

use Doctrine\ORM\Mapping as ORM;

trait SchedulableJobTrait
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $scheduleExpression = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $scheduleTimezone = null;


    public function getScheduleExpression(): ?string
    {
        return $this->scheduleExpression;
    }

    public function setScheduleExpression(?string $scheduleExpression): static
    {
        $this->scheduleExpression = $scheduleExpression;

        return $this;
    }

    public function getScheduleTimezone(): string
    {
        return $this->scheduleTimezone ?? 'UTC';
    }

    public function setScheduleTimezone(?string $scheduleTimezone): static
    {
        $this->scheduleTimezone = $scheduleTimezone;

        return $this;
    }
}
