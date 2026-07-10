<?php

declare(strict_types=1);

namespace Syriable\Metrics\Formulas;

use Syriable\Metrics\Contracts\FormulaEvaluator;
use Syriable\Metrics\Exceptions\InvalidFormulaException;

/**
 * A small, safe arithmetic evaluator (shunting-yard → RPN). No eval(), no
 * SQL, no callbacks — a formula can only combine dataset values.
 *
 * Grammar: numbers, dataset references (bare identifiers or [bracketed
 * names with spaces]), + - * / %, unary minus, parentheses.
 *
 * Null semantics: any null operand yields null (an unknown grows from an
 * unknown), and division/modulo by zero yields null rather than throwing —
 * a metric with an empty denominator is "no data", not an error.
 */
final class DefaultFormulaEvaluator implements FormulaEvaluator
{
    private const OPERATORS = [
        '+' => ['precedence' => 1, 'rightAssociative' => false],
        '-' => ['precedence' => 1, 'rightAssociative' => false],
        '*' => ['precedence' => 2, 'rightAssociative' => false],
        '/' => ['precedence' => 2, 'rightAssociative' => false],
        '%' => ['precedence' => 2, 'rightAssociative' => false],
        'u-' => ['precedence' => 3, 'rightAssociative' => true],
    ];

    public function evaluate(string $expression, array $variables): ?float
    {
        return $this->run($this->toPostfix($expression, $this->tokenize($expression)), $expression, $variables);
    }

    /**
     * @return list<array{type: 'number'|'variable'|'operator'|'paren', value: string}>
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $i = 0;

        while ($i < $length) {
            $char = $expression[$i];

            if (ctype_space($char)) {
                $i++;
            } elseif (preg_match('/\G\d+(\.\d+)?/', $expression, $m, 0, $i) === 1) {
                $tokens[] = ['type' => 'number', 'value' => $m[0]];
                $i += strlen($m[0]);
            } elseif (preg_match('/\G[A-Za-z_][A-Za-z0-9_.]*/', $expression, $m, 0, $i) === 1) {
                $tokens[] = ['type' => 'variable', 'value' => $m[0]];
                $i += strlen($m[0]);
            } elseif ($char === '[') {
                $close = strpos($expression, ']', $i);

                if ($close === false) {
                    throw InvalidFormulaException::malformed($expression);
                }

                $tokens[] = ['type' => 'variable', 'value' => trim(substr($expression, $i + 1, $close - $i - 1))];
                $i = $close + 1;
            } elseif (str_contains('+-*/%', $char)) {
                $tokens[] = ['type' => 'operator', 'value' => $char];
                $i++;
            } elseif ($char === '(' || $char === ')') {
                $tokens[] = ['type' => 'paren', 'value' => $char];
                $i++;
            } else {
                throw InvalidFormulaException::unexpectedToken($expression, $char);
            }
        }

        return $tokens;
    }

    /**
     * @param  list<array{type: string, value: string}>  $tokens
     * @return list<array{type: string, value: string}>
     */
    private function toPostfix(string $expression, array $tokens): array
    {
        $output = [];
        $stack = [];
        $previous = null;

        foreach ($tokens as $token) {
            if ($token['type'] === 'number' || $token['type'] === 'variable') {
                $output[] = $token;
            } elseif ($token['type'] === 'operator') {
                $operator = $token['value'];

                // A minus at the start, after another operator, or after "("
                // is unary negation.
                if ($operator === '-' && ($previous === null || $previous['type'] === 'operator' || $previous['value'] === '(')) {
                    $operator = 'u-';
                }

                $spec = self::OPERATORS[$operator];

                while ($stack !== []) {
                    $top = end($stack);

                    if ($top === '(' || (self::OPERATORS[$top]['precedence'] < $spec['precedence'])
                        || (self::OPERATORS[$top]['precedence'] === $spec['precedence'] && $spec['rightAssociative'])) {
                        break;
                    }

                    $output[] = ['type' => 'operator', 'value' => array_pop($stack)];
                }

                $stack[] = $operator;
            } elseif ($token['value'] === '(') {
                $stack[] = '(';
            } else {
                while (($top = array_pop($stack)) !== '(') {
                    if ($top === null) {
                        throw InvalidFormulaException::malformed($expression);
                    }

                    $output[] = ['type' => 'operator', 'value' => $top];
                }
            }

            $previous = $token;
        }

        while (($top = array_pop($stack)) !== null) {
            if ($top === '(') {
                throw InvalidFormulaException::malformed($expression);
            }

            $output[] = ['type' => 'operator', 'value' => $top];
        }

        return $output;
    }

    /**
     * @param  list<array{type: string, value: string}>  $postfix
     * @param  array<string, int|float|null>  $variables
     */
    private function run(array $postfix, string $expression, array $variables): ?float
    {
        /** @var list<float|null> $stack */
        $stack = [];

        foreach ($postfix as $token) {
            if ($token['type'] === 'number') {
                $stack[] = (float) $token['value'];
            } elseif ($token['type'] === 'variable') {
                if (! array_key_exists($token['value'], $variables)) {
                    throw InvalidFormulaException::unknownVariable($expression, $token['value']);
                }

                $value = $variables[$token['value']];
                $stack[] = $value === null ? null : (float) $value;
            } elseif ($token['value'] === 'u-') {
                if (count($stack) < 1) {
                    throw InvalidFormulaException::malformed($expression);
                }

                $operand = array_pop($stack);
                $stack[] = $operand === null ? null : -$operand;
            } else {
                if (count($stack) < 2) {
                    throw InvalidFormulaException::malformed($expression);
                }

                $right = array_pop($stack);
                $left = array_pop($stack);
                $stack[] = $this->apply($token['value'], $left, $right);
            }
        }

        if (count($stack) !== 1) {
            throw InvalidFormulaException::malformed($expression);
        }

        return $stack[0];
    }

    private function apply(string $operator, ?float $left, ?float $right): ?float
    {
        if ($left === null || $right === null) {
            return null;
        }

        return match ($operator) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right == 0.0 ? null : $left / $right,
            '%' => $right == 0.0 ? null : fmod($left, $right),
            default => null,
        };
    }
}
