<?php

namespace App\InjectableExpression;

use App\Service\CountersRepository;
use RuntimeException;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

final readonly class CounterInjectableExpression implements InjectableExpression
{
    public function __construct(
        private CountersRepository $repository,
    ) {
    }

    public function getName(): string
    {
        return 'Counter';
    }

    public function getFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            $this->getName(),
            static fn () => throw new RuntimeException('This function cannot be compiled'),
            function (array $arguments, string $counterName): string {
                $counter = $this->repository->findByName($counterName);
                if ($counter === null) {
                    return '0';
                }

                return $counter->value;
            },
        );
    }
}
