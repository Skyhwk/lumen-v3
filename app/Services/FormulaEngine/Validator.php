<?php

namespace App\Services\FormulaEngine;

class Validator
{
    private FunctionRegistry $registry;

    public function __construct(FunctionRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function validate(array $tokens, array $allowedVariables): array
    {
        $errors = [];

        if (empty($tokens)) {
            return $this->invalid([$this->error('EMPTY_FORMULA', 'Formula kosong.')]);
        }

        $allowedMap = array_fill_keys($allowedVariables, true);

        $parenCount = 0;
        $prevType = null;

        foreach ($tokens as $index => $token) {
            $type = $token['type'];
            $value = $token['value'] ?? null;

            if ($type === 'LPAREN') {
                $parenCount++;
            }

            if ($type === 'RPAREN') {
                $parenCount--;
                if ($parenCount < 0) {
                    $errors[] = $this->error('UNBALANCED_PAREN', 'Kurung belum ditutup.', $token['position'] ?? null, $index);
                }
            }

            if ($type === 'VARIABLE' && !isset($allowedMap[$value])) {
                $errors[] = $this->error('UNKNOWN_VARIABLE', 'Variable "' . $value . '" tidak ditemukan.', $token['position'] ?? null, $index);
            }

            if ($type === 'FUNCTION' && !$this->registry->has($value)) {
                $errors[] = $this->error('UNKNOWN_FUNCTION', 'Function "' . $value . '" tidak dikenal.', $token['position'] ?? null, $index);
            }

            if ($type === 'OPERATOR') {
                if ($this->isBinaryOperator($value) && $this->isOperatorType($prevType) && !$this->allowsUnaryAfter($prevType, $value)) {
                    $errors[] = $this->error('CONSECUTIVE_OPERATOR', 'Operator ganda.', $token['position'] ?? null, $index);
                }
            }

            if ($prevType !== null) {
                if ($this->expectsOperatorBetween($prevType, $type)) {
                    $errors[] = $this->error('MISSING_OPERAND', 'Formula tidak valid.', $token['position'] ?? null, $index);
                }
            }

            $prevType = $type;
        }

        if ($parenCount > 0) {
            $errors[] = $this->error('UNBALANCED_PAREN', 'Kurung belum ditutup.');
        }

        if ($this->isOperatorType($prevType) && ($tokens[count($tokens) - 1]['value'] ?? null) !== '-') {
            $errors[] = $this->error('TRAILING_OPERATOR', 'Formula tidak valid.');
        }

        if (!empty($errors)) {
            return $this->invalid($errors);
        }

        return ['valid' => true, 'errors' => []];
    }

    public function validateAst(AstNode $node, FunctionRegistry $registry): void
    {
        if ($node->type === 'FUNCTION') {
            $handler = $registry->get($node->name);
            $count = count($node->args);
            if ($count < $handler->minArgs() || $count > $handler->maxArgs()) {
                throw new FormulaException(
                    'Function ' . $node->name . ' membutuhkan ' . $handler->minArgs() . '-' . $handler->maxArgs() . ' argumen.',
                    'INVALID_ARG_COUNT'
                );
            }

            foreach ($node->args as $arg) {
                $this->validateAst($arg, $registry);
            }
        }

        if ($node->type === 'BINARY') {
            $this->validateAst($node->left, $registry);
            $this->validateAst($node->right, $registry);
        }

        if ($node->type === 'UNARY') {
            $this->validateAst($node->operand, $registry);
        }
    }

    private function invalid(array $errors): array
    {
        return ['valid' => false, 'errors' => $errors];
    }

    private function error(string $code, string $message, ?int $position = null, ?int $tokenIndex = null): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'position' => $position,
            'tokenIndex' => $tokenIndex,
        ];
    }

    private function isOperatorType(?string $type): bool
    {
        return in_array($type, ['OPERATOR', 'LPAREN', 'COMMA', 'FUNCTION'], true);
    }

    private function isBinaryOperator(string $operator): bool
    {
        return in_array($operator, ['+', '*', '/', '%', '^'], true) || $operator === '-';
    }

    private function allowsUnaryAfter(?string $prevType, string $operator): bool
    {
        return $operator === '-' && in_array($prevType, ['OPERATOR', 'LPAREN', 'COMMA', 'FUNCTION'], true);
    }

    private function expectsOperatorBetween(string $prevType, string $currentType): bool
    {
        $prevEndsValue = in_array($prevType, ['NUMBER', 'VARIABLE', 'RPAREN'], true);
        $currStartsValue = in_array($currentType, ['NUMBER', 'VARIABLE', 'LPAREN', 'FUNCTION'], true);

        if (!$prevEndsValue || !$currStartsValue) {
            return false;
        }

        if ($prevType === 'RPAREN' && $currentType === 'RPAREN') {
            return false;
        }

        if ($currentType === 'RPAREN' && in_array($prevType, ['NUMBER', 'VARIABLE'], true)) {
            return false;
        }

        return true;
    }
}
