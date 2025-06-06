<?php

namespace App\InjectableExpression;

use App\Repository\CounterRepository;
use App\Service\CurrentUserService;
use RuntimeException;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

final readonly class CounterInjectableExpression implements InjectableExpression
{
    public function __construct(
        private CounterRepository $repository,
        private CurrentUserService $currentUserService,
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
                $user = $this->currentUserService->getCurrentUser();
                if ($user === null) {
                    return '0';
                }

                $counter = $this->repository->findOneBy(['name' => $counterName, 'userId' => $user->getUserIdentifier()]);
                if ($counter === null) {
                    return '0';
                }

                return (string) $counter->getValue();
            },
        );
    }
}
