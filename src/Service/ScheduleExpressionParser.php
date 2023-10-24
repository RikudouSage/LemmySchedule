<?php

namespace App\Service;

use App\Enum\ScheduleType;
use App\Enum\Weekday;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class ScheduleExpressionParser
{
    private const ALL = '*';

    private const YEAR = 'Y';

    private const MONTH = 'n';

    private const DAY_OF_WEEK = 'N';

    private const LAST_DAY_OF_MONTH = 't';

    public function getNextRunDate(
        string $expression,
        DateTimeInterface $now = new DateTimeImmutable(),
        int $nth = 0,
        DateTimeZone $timeZone = new DateTimeZone('UTC'),
    ): DateTimeInterface {
        [
            $type,
            $minute,
            $hour,
            $dayOfMonth,
            $monthOrdinal,
            $dayOfWeek,
            $weekOrdinal,
        ] = explode(' ', $expression);
        $type = ScheduleType::from((int) $type);
        if ($dayOfWeek !== self::ALL) {
            $dayOfWeek = Weekday::from((int) $dayOfWeek);
        }

        $now = DateTimeImmutable::createFromInterface($now)->setTimezone($timeZone);
        $dateTime = DateTimeImmutable::createFromInterface($now)
            ->setTimezone($timeZone)
            ->setTime($hour, $minute);

        switch ($type) {
            case ScheduleType::Day:
                if ($dayOfMonth !== self::ALL) {
                    $dateTime = $dateTime->add(new DateInterval("P{$dayOfMonth}D"));
                }
                if ($dateTime <= $now) {
                    $dateTime = $dateTime->add(new DateInterval('P1D'));
                }
                break;
            case ScheduleType::Month:
                if ($monthOrdinal !== self::ALL) {
                    $dateTime = $dateTime->add(new DateInterval("P{$monthOrdinal}M"));
                }
                $targetDayOfMonth = $dayOfMonth;
                if ($targetDayOfMonth === 'L') {
                    $targetDayOfMonth = $dateTime->format(self::LAST_DAY_OF_MONTH);
                }
                $dateTime = $dateTime->setDate(
                    $dateTime->format(self::YEAR),
                    $dateTime->format(self::MONTH),
                    $targetDayOfMonth,
                );
                if ($dateTime <= $now) {
                    if ($dayOfMonth === 'L') {
                        $dateTime = $dateTime->setDate(
                            $dateTime->format(self::YEAR),
                            (int) $dateTime->format(self::MONTH) + 1,
                            1
                        );
                        $dateTime = $dateTime->setDate(
                            $dateTime->format(self::YEAR),
                            $dateTime->format(self::MONTH),
                            $dateTime->format(self::LAST_DAY_OF_MONTH),
                        );
                    } else {
                        $dateTime = $dateTime->add(new DateInterval('P1M'));
                    }
                }
                break;
            case ScheduleType::Week:
                $currentDay = Weekday::from((int) $dateTime->format(self::DAY_OF_WEEK));
                $diff = $currentDay->value - $dayOfWeek->value;
                $modifierFunction = $dateTime->sub(...);
                if ($diff < 0) {
                    $diff = abs($diff);
                    $modifierFunction = $dateTime->add(...);
                }
                $dateTime = $modifierFunction(new DateInterval("P{$diff}D"));
                if ($weekOrdinal !== self::ALL) {
                    $daysToAdd = $weekOrdinal * 7;
                    $dateTime = $dateTime->add(new DateInterval("P{$daysToAdd}D"));
                }
                if ($dateTime <= $now) {
                    $dateTime = $dateTime->add(new DateInterval('P7D'));
                }
                break;
        }

        if ($nth !== 0) {
            return $this->getNextRunDate(expression: $expression, now: $dateTime, nth: $nth - 1, timeZone: $timeZone);
        }

        return $dateTime->setTimezone($timeZone);
    }
}
