<?php

namespace App\Service;

use App\InjectableExpression\InjectableExpression;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

final class TitleExpressionLanguage extends ExpressionLanguage
{
    /**
     * @param iterable<InjectableExpression> $expressions
     */
    public function __construct(
        #[TaggedIterator('app.injectable_expression')]
        private readonly iterable $expressions,
        ?CacheItemPoolInterface $cache = null,
    ) {
        parent::__construct($cache, []);
    }

    protected function registerFunctions(): void
    {
        foreach ($this->expressions as $expression) {
            $this->addFunction($expression->getFunction());
        }
    }
}
