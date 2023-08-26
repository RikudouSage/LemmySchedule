<?php

namespace App\Component;

use App\Dto\Time;
use App\Enum\DayType;
use App\Enum\ScheduleType;
use App\Enum\Weekday;
use App\Service\ScheduleExpressionParser;
use DateTimeInterface;
use DateTimeZone;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[AsLiveComponent]
final class ScheduleComponent
{
    #[LiveProp(writable: true)]
    public int $amount = 1;

    #[LiveProp(writable: true)]
    public ScheduleType $scheduleType = ScheduleType::Day;

    #[LiveProp]
    public ?DateTimeZone $timeZone = null;

    #[LiveProp(writable: true)]
    public Weekday $weekday = Weekday::Monday;

    #[LiveProp(writable: true)]
    public ?Time $targetTime = null;

    #[LiveProp(writable: true)]
    public DayType $selectedDayType = DayType::SpecificDay;

    #[LiveProp(writable: true)]
    public int $day = 1;

    public int $maxAmount = 31;

    public ?string $scheduleExpression = null;

    public bool $timezoneError = false;

    /**
     * @var array<DateTimeInterface>
     */
    public array $nextRunTimes = [];

    public function __construct(
        private readonly ScheduleExpressionParser $scheduleExpressionParser,
    ) {
    }

    public function __invoke(): void
    {
        $this->timeZone ??= new DateTimeZone('UTC');
        $this->maxAmount = match ($this->scheduleType) {
            ScheduleType::Week => 4,
            ScheduleType::Month => 12,
            ScheduleType::Day => 31,
        };
        if ($this->amount > $this->maxAmount) {
            $this->amount = $this->maxAmount;
        }
        $this->scheduleExpression = $this->buildScheduleExpression();
        if ($this->scheduleExpression) {
            $this->nextRunTimes = [
                $this->scheduleExpressionParser->getNextRunDate($this->scheduleExpression, nth: 0, timeZone: $this->timeZone),
                $this->scheduleExpressionParser->getNextRunDate($this->scheduleExpression, nth: 1, timeZone: $this->timeZone),
                $this->scheduleExpressionParser->getNextRunDate($this->scheduleExpression, nth: 2, timeZone: $this->timeZone),
                $this->scheduleExpressionParser->getNextRunDate($this->scheduleExpression, nth: 3, timeZone: $this->timeZone),
                $this->scheduleExpressionParser->getNextRunDate($this->scheduleExpression, nth: 4, timeZone: $this->timeZone),
            ];
        }
    }

    private function buildScheduleExpression(): ?string
    {
        if ($this->targetTime === null) {
            return null;
        }

        $expression = [
            $this->scheduleType->value,
            $this->targetTime->minutes,
            $this->targetTime->hours,
        ];

        $expression[] = match ($this->scheduleType) { // day of the month (1-31)
            ScheduleType::Day => match ($this->amount) {
                1 => '*',
                default => "{$this->amount}",
            },
            ScheduleType::Month => match ($this->selectedDayType) {
                DayType::SpecificDay => $this->day,
                DayType::LastDay => 'L',
            },
            ScheduleType::Week => '*',
        };
        $expression[] = match ($this->scheduleType) { // month (1-12)
            ScheduleType::Day, ScheduleType::Week => '*',
            ScheduleType::Month => match ($this->amount) {
                1 => '*',
                default => "{$this->amount}",
            },
        };
        $expression[] = match ($this->scheduleType) { // day of the week (0-6, Sunday=0)
            ScheduleType::Day, ScheduleType::Month => '*',
            ScheduleType::Week => $this->weekday->value,
        };

        $expression[] = match ($this->scheduleType) { // every nth week
            ScheduleType::Day, ScheduleType::Month => '*',
            ScheduleType::Week => match ($this->amount) {
                1 => '*',
                default => "{$this->amount}",
            },
        };

        return implode(' ', $expression);
    }

    #[LiveAction]
    public function setTimezoneAsString(#[LiveArg] string $timezone): void
    {
        $this->timeZone = new DateTimeZone($timezone);
    }

    #[LiveAction]
    public function showTimezoneError(): void
    {
        $this->timezoneError = true;
    }
}
