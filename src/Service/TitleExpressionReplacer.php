<?php

namespace App\Service;

use App\Dto\TitleExpressionParserResult;
use App\InjectableExpression\InjectableExpression;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final readonly class TitleExpressionReplacer
{
    /**
     * @var array<string>
     */
    private array $expressions;

    /**
     * @param iterable<InjectableExpression> $expressions
     */
    public function __construct(
        #[TaggedIterator('app.injectable_expression')]
        iterable $expressions,
        private TitleExpressionLanguage $expressionLanguage,
    ) {
        $this->expressions = array_map(
            static fn (InjectableExpression $expression) => $expression->getName(),
            [...$expressions],
        );
    }

    public function parse(string $title): TitleExpressionParserResult
    {
        $regex = /** @lang RegExp */ '@#\[(?<FunctionName>[a-zA-Z]+)\((?<Params>[^\)]*?)\)\]#@';
        if (!preg_match_all($regex, $title, $matches)) {
            return new TitleExpressionParserResult();
        }

        $valid = [];
        $invalid = [];
        for ($i = 0; $i < count($matches['FunctionName']); ++$i) {
            $functionName = $matches['FunctionName'][$i];
            if (in_array($functionName, $this->expressions, true)) {
                $valid[] = $matches[0][$i];
            } else {
                $invalid[] = $matches[0][$i];
            }
        }

        return new TitleExpressionParserResult($valid, $invalid);
    }

    public function replace(string $title, ?TitleExpressionParserResult $parserResult = null): string
    {
        $parserResult ??= $this->parse($title);
        foreach ($parserResult->validExpressions as $validExpression) {
            $expression = substr($validExpression, 2, -2);
            $result = $this->expressionLanguage->evaluate($expression);
            $title = str_replace($validExpression, $result, $title);
        }

        return $title;
    }
}
