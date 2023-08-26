<?php

namespace App\Service;

use App\Dto\Time;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use RuntimeException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class TwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_date_time', $this->formatDate(...)),
            new TwigFilter('timezone_offset', $this->getTimezoneOffset(...)),
        ];
    }

    private function formatDate(string|DateTimeInterface $dateTime, string $locale = 'en-US'): string
    {
        if (is_string($dateTime)) {
            $dateTime = new DateTimeImmutable($dateTime);
        }

        $formatter = new IntlDateFormatter(
            locale: $locale,
            dateType: IntlDateFormatter::FULL,
            timeType: IntlDateFormatter::SHORT,
        );
        $formatter->setTimeZone($dateTime->getTimezone());

        return $formatter->format($dateTime) ?: throw new RuntimeException("Failed formatting datetime: {$dateTime->format('c')}");
    }

    private function getTimezoneOffset(DateTimeZone $timeZone): Time
    {
        $offset = $timeZone->getOffset(new DateTimeImmutable(timezone: $timeZone));
        $hours = floor($offset / 60 / 60);
        $remainder = $offset - $hours * 60 * 60;
        $minutes = ceil($remainder / 60);

        return new Time($hours, $minutes);
    }
}
