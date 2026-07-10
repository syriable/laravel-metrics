<?php

declare(strict_types=1);

use Syriable\Metrics\Exceptions\InvalidFormulaException;
use Syriable\Metrics\Formulas\DefaultFormulaEvaluator;

beforeEach(function (): void {
    $this->evaluator = new DefaultFormulaEvaluator;
});

it('evaluates arithmetic with correct precedence', function (): void {
    expect($this->evaluator->evaluate('2 + 3 * 4', []))->toBe(14.0)
        ->and($this->evaluator->evaluate('(2 + 3) * 4', []))->toBe(20.0)
        ->and($this->evaluator->evaluate('10 - 4 - 3', []))->toBe(3.0)
        ->and($this->evaluator->evaluate('10 % 3', []))->toBe(1.0);
});

it('resolves bare and bracketed dataset references', function (): void {
    $variables = ['revenue' => 100, 'net profit' => 40];

    expect($this->evaluator->evaluate('revenue * 2', $variables))->toBe(200.0)
        ->and($this->evaluator->evaluate('[net profit] / revenue * 100', $variables))->toBe(40.0);
});

it('supports unary minus', function (): void {
    expect($this->evaluator->evaluate('-5 + 10', []))->toBe(5.0)
        ->and($this->evaluator->evaluate('10 * -2', []))->toBe(-20.0)
        ->and($this->evaluator->evaluate('-(3 + 4)', []))->toBe(-7.0);
});

it('propagates null and treats division by zero as null', function (): void {
    expect($this->evaluator->evaluate('a + 1', ['a' => null]))->toBeNull()
        ->and($this->evaluator->evaluate('10 / a', ['a' => 0]))->toBeNull()
        ->and($this->evaluator->evaluate('10 % a', ['a' => 0]))->toBeNull();
});

it('rejects unknown variables', function (): void {
    $this->evaluator->evaluate('missing + 1', []);
})->throws(InvalidFormulaException::class, 'unknown dataset');

it('rejects malformed expressions', function (string $expression): void {
    $this->evaluator->evaluate($expression, []);
})->with(['1 +', '(1 + 2', '1 2', '[oops', ')('])->throws(InvalidFormulaException::class);
