<?php

namespace App\InjectableExpression;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;

final class DateInjectableExpression implements InjectableExpression
{
    public function getName(): string
    {
        return 'InjectDate';
    }

    public function getFunction(): ExpressionFunction
    {
        return new ExpressionFunction($this->getName(), static function (string $format): string {
            return sprintf('date(%s)', $format);
        }, static function (array $arguments, string $format): string {
            return date($format);
        });
    }
}
