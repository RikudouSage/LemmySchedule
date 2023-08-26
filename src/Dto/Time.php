<?php

namespace App\Dto;

use InvalidArgumentException;
use LogicException;
use Stringable;

final class Time implements Stringable
{
    public function __construct(
        public ?int $hours = null,
        public ?int $minutes = null,
        ?string $formatted = null,
    ) {
        if ($formatted) {
            $instance = self::fromString($formatted);

            $notMatchingException = new LogicException("You provided both formatted and unformatted values and the values don't match.");
            if ($this->hours && $this->hours !== $instance->hours) {
                throw $notMatchingException;
            }
            if ($this->minutes && $this->minutes !== $instance->minutes) {
                throw $notMatchingException;
            }

            $this->hours = $instance->hours;
            $this->minutes = $instance->minutes;
        }
    }

    public function isPositive(): bool
    {
        return ($this->hours ?? 0) >= 0;
    }

    public function toString(): string
    {
        $result = '';
        if (($this->hours ?? 0) < 0) {
            $result = '-';
        }

        return $result . sprintf(
            '%02d:%02d',
            abs($this->hours ?? 0),
            $this->minutes ?? 0,
        );
    }

    public static function fromString(string $formatted): self
    {
        [$hours, $minutes, $seconds] = array_pad(explode(':', $formatted), 3, null);
        if (!is_numeric($hours) || !is_numeric($minutes) || (!is_numeric($seconds) && $seconds !== null)) {
            throw new InvalidArgumentException("Invalid time string: {$formatted}");
        }

        return new self(hours: (int) $hours, minutes: (int) $minutes);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
