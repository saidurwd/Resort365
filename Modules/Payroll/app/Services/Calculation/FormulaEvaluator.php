<?php

namespace Modules\Payroll\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Payroll\Exceptions\PayrollRuleViolated;

/**
 * Works out a salary formula such as `BASIC * 0.5` or `MIN(BASIC * 0.1, 10000)` (Step 6.4). Pure, and safe: it reads only
 * numbers, component codes, `+ - * /`, brackets and the functions MIN, MAX, ROUND and ABS, so a formula can never run code.
 */
final class FormulaEvaluator
{
    private const int SCALE = 10;

    /** @var list<string> */
    private array $tokens = [];

    private int $position = 0;

    /** @var array<string, BigDecimal> */
    private array $values = [];

    /**
     * @param  array<string, string>  $values  component code => amount
     *
     * @throws PayrollRuleViolated
     */
    public function evaluate(string $formula, array $values): string
    {
        $this->tokens = $this->tokenise($formula);
        $this->position = 0;
        $this->values = array_map(BigDecimal::of(...), array_change_key_case($values, CASE_UPPER));

        if ($this->tokens === []) {
            throw new PayrollRuleViolated(__('The formula is empty.'));
        }

        $result = $this->expression();

        $left = $this->peek();

        if ($left !== null) {
            throw new PayrollRuleViolated(__('The formula has something unexpected near ":token".', ['token' => $left]));
        }

        return (string) $result->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    /**
     * The component codes a formula uses.
     *
     * @return list<string>
     *
     * @throws PayrollRuleViolated
     */
    public function references(string $formula): array
    {
        $tokens = $this->tokenise($formula);
        $codes = [];

        foreach ($tokens as $index => $token) {
            if (preg_match('/^[A-Za-z_]/', $token) === 1 && ($tokens[$index + 1] ?? '') !== '(') {
                $codes[] = strtoupper($token);
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @return list<string>
     *
     * @throws PayrollRuleViolated
     */
    private function tokenise(string $formula): array
    {
        $rest = trim($formula);
        $tokens = [];

        while ($rest !== '') {
            if (preg_match('/^\s*(\d+(?:\.\d+)?|[A-Za-z_][A-Za-z0-9_]*|[-+*\/(),])/', $rest, $match) !== 1) {
                throw new PayrollRuleViolated(__('The formula has a character it does not understand: ":text".', ['text' => mb_substr(trim($rest), 0, 12)]));
            }

            $tokens[] = $match[1];
            $rest = substr($rest, strlen($match[0]));
            $rest = ltrim($rest);
        }

        return $tokens;
    }

    private function expression(): BigDecimal
    {
        $value = $this->term();

        while (in_array($this->peek(), ['+', '-'], true)) {
            $operator = $this->next();
            $right = $this->term();
            $value = $operator === '+' ? $value->plus($right) : $value->minus($right);
        }

        return $value;
    }

    private function term(): BigDecimal
    {
        $value = $this->unary();

        while (in_array($this->peek(), ['*', '/'], true)) {
            $operator = $this->next();
            $right = $this->unary();

            if ($operator === '/') {
                if ($right->isZero()) {
                    throw new PayrollRuleViolated(__('The formula divides by zero.'));
                }

                $value = $value->dividedBy($right, self::SCALE, RoundingMode::HalfUp);
            } else {
                $value = $value->multipliedBy($right);
            }
        }

        return $value;
    }

    private function unary(): BigDecimal
    {
        if ($this->peek() === '-') {
            $this->next();

            return $this->unary()->negated();
        }

        if ($this->peek() === '+') {
            $this->next();

            return $this->unary();
        }

        return $this->primary();
    }

    private function primary(): BigDecimal
    {
        $token = $this->next();

        if ($token === null) {
            throw new PayrollRuleViolated(__('The formula ends too soon.'));
        }

        if ($token === '(') {
            $value = $this->expression();
            $this->expect(')');

            return $value;
        }

        if (is_numeric($token)) {
            return BigDecimal::of($token);
        }

        if (preg_match('/^[A-Za-z_]/', $token) !== 1) {
            throw new PayrollRuleViolated(__('The formula has something unexpected near ":token".', ['token' => $token]));
        }

        if ($this->peek() === '(') {
            return $this->call(strtoupper($token));
        }

        $code = strtoupper($token);

        return $this->values[$code] ?? throw new PayrollRuleViolated(__('The formula uses ":code", which has no amount here.', ['code' => $code]));
    }

    private function call(string $function): BigDecimal
    {
        $this->expect('(');
        $arguments = [$this->expression()];

        while ($this->peek() === ',') {
            $this->next();
            $arguments[] = $this->expression();
        }

        $this->expect(')');

        return match ($function) {
            'MIN' => array_reduce($arguments, fn (?BigDecimal $carry, BigDecimal $value): BigDecimal => ! $carry instanceof BigDecimal || $value->isLessThan($carry) ? $value : $carry),
            'MAX' => array_reduce($arguments, fn (?BigDecimal $carry, BigDecimal $value): BigDecimal => ! $carry instanceof BigDecimal || $value->isGreaterThan($carry) ? $value : $carry),
            'ABS' => $arguments[0]->abs(),
            'ROUND' => $arguments[0]->toScale(isset($arguments[1]) ? max(0, min(6, $arguments[1]->toInt())) : 0, RoundingMode::HalfUp),
            default => throw new PayrollRuleViolated(__('The formula uses a function that does not exist: :name.', ['name' => $function])),
        } ?? throw new PayrollRuleViolated(__('The formula calls :name with nothing to work on.', ['name' => $function]));
    }

    private function peek(): ?string
    {
        return $this->tokens[$this->position] ?? null;
    }

    private function next(): ?string
    {
        return $this->tokens[$this->position++] ?? null;
    }

    private function expect(string $token): void
    {
        if ($this->next() !== $token) {
            throw new PayrollRuleViolated(__('The formula is missing a ":token".', ['token' => $token]));
        }
    }
}
