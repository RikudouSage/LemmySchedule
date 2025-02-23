<?php

namespace App\Service;

use App\Dto\Time;
use App\Enum\Feature;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use LogicException;
use ReflectionEnum;
use RuntimeException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use function ReflectionClass;

final class TwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly SupportedFeaturesManager $featuresManager,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_date_time', $this->formatDate(...)),
            new TwigFilter('timezone_offset', $this->getTimezoneOffset(...)),
        ];
    }

    public function getTests(): array
    {
        return [
            new TwigTest('supported', $this->isFeatureSupported(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('enum', $this->getEnum(...)),
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

    private function isFeatureSupported(Feature $feature): bool
    {
        return $this->featuresManager->supports($feature);
    }

    private function getEnum(string $class): object
    {
        if (!enum_exists($class)) {
            throw new LogicException("The enum '{$class}' does not exist");
        }

        return new readonly class($class)
        {
            public function __construct(
                private string $class,
            ) {
            }

            public function __call(string $name, array $arguments)
            {
                return (new ReflectionEnum($this->class))->getCase($name)->getValue();
            }
        };
    }
}
