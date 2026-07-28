<?php

namespace App\Services\FormulaEngine;

class Lexer
{
    private const MAX_LENGTH = 2000;

    public function tokenize(string $formula): array
    {
        $formula = trim($formula);
        if ($formula === '') {
            return [];
        }

        if (strlen($formula) > self::MAX_LENGTH) {
            throw new FormulaException('Formula terlalu panjang.', 'FORMULA_TOO_LONG');
        }

        $tokens = [];
        $length = strlen($formula);
        $i = 0;

        while ($i < $length) {
            $char = $formula[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            if ($char === '{') {
                if (!preg_match('/^\{([a-z][a-z0-9_]*)\}/', substr($formula, $i), $matches)) {
                    throw new FormulaException('Variable tidak valid.', 'ILLEGAL_CHAR', $i);
                }

                $tokens[] = [
                    'type' => 'VARIABLE',
                    'value' => $matches[1],
                    'position' => $i,
                ];
                $i += strlen($matches[0]);
                continue;
            }

            if (ctype_digit($char) || ($char === '.' && $i + 1 < $length && ctype_digit($formula[$i + 1]))) {
                if (!preg_match('/^[0-9]+(\.[0-9]+)?/', substr($formula, $i), $matches)) {
                    throw new FormulaException('Angka tidak valid.', 'ILLEGAL_CHAR', $i);
                }

                $tokens[] = [
                    'type' => 'NUMBER',
                    'value' => (float) $matches[0],
                    'raw' => $matches[0],
                    'position' => $i,
                ];
                $i += strlen($matches[0]);
                continue;
            }

            if (ctype_alpha($char)) {
                if (!preg_match('/^[A-Za-z][A-Za-z0-9]*/', substr($formula, $i), $matches)) {
                    throw new FormulaException('Formula tidak valid.', 'ILLEGAL_CHAR', $i);
                }

                $name = strtoupper($matches[0]);
                $nextIndex = $i + strlen($matches[0]);
                while ($nextIndex < $length && ctype_space($formula[$nextIndex])) {
                    $nextIndex++;
                }

                if ($nextIndex >= $length || $formula[$nextIndex] !== '(') {
                    throw new FormulaException('Formula tidak valid.', 'ILLEGAL_CHAR', $i);
                }

                $tokens[] = [
                    'type' => 'FUNCTION',
                    'value' => $name,
                    'position' => $i,
                ];
                $i += strlen($matches[0]);
                continue;
            }

            if ($char === '(') {
                $tokens[] = ['type' => 'LPAREN', 'value' => '(', 'position' => $i];
                $i++;
                continue;
            }

            if ($char === ')') {
                $tokens[] = ['type' => 'RPAREN', 'value' => ')', 'position' => $i];
                $i++;
                continue;
            }

            if ($char === ',') {
                $tokens[] = ['type' => 'COMMA', 'value' => ',', 'position' => $i];
                $i++;
                continue;
            }

            if (in_array($char, ['+', '-', '*', '/', '%', '^'], true)) {
                $tokens[] = ['type' => 'OPERATOR', 'value' => $char, 'position' => $i];
                $i++;
                continue;
            }

            throw new FormulaException('Formula tidak valid.', 'ILLEGAL_CHAR', $i);
        }

        if (count($tokens) > 500) {
            throw new FormulaException('Formula terlalu kompleks.', 'TOO_MANY_TOKENS');
        }

        return $tokens;
    }
}
