<?php

namespace App\InjectableExpression;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

#[AutoconfigureTag('app.injectable_expression')]
interface InjectableExpression
{
    public function getName(): string;

    public function getFunction(): ExpressionFunction;
}
